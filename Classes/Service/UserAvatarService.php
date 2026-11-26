<?php

declare(strict_types=1);

namespace UpAssist\Neos\UserAvatar\Service;

use Imagine\Filter\Basic\Autorotate;
use Imagine\Image\Box;
use Imagine\Image\ImagineInterface;
use Imagine\Image\ManipulatorInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Model\UserPreferences;
use Neos\Neos\Domain\Service\UserService;

/**
 * Stores, removes and resolves the profile photo of a Neos backend user.
 *
 * The photo is a square image resource whose identifier is kept in the user's preferences,
 * so the package needs no database table of its own. It is not a Media asset: it does not
 * show up in the Media module.
 */
#[Flow\Scope('singleton')]
class UserAvatarService
{
    public const PREFERENCE_KEY = 'UpAssist.Neos.UserAvatar.resource';

    /**
     * JPEG and PNG only: GIF and WebP can hold many frames, which the pixel cap below does not count.
     */
    public const ALLOWED_MEDIA_TYPES = ['image/jpeg', 'image/png'];

    /** The resource collection that holds only avatar photos (see Settings.yaml). */
    public const COLLECTION = 'upassistUserAvatar';

    /** Uploads with more pixels than this are refused before they are decoded. */
    private const MAXIMUM_PIXELS = 40_000_000;

    #[Flow\Inject]
    protected UserService $userService;

    #[Flow\Inject]
    protected ResourceManager $resourceManager;

    #[Flow\Inject]
    protected PersistenceManagerInterface $persistenceManager;

    #[Flow\Inject]
    protected ImagineInterface $imagine;

    #[Flow\InjectConfiguration(path: 'size')]
    protected int $size;

    #[Flow\InjectConfiguration(path: 'maximumUploadSize')]
    protected int $maximumUploadSize;

    #[Flow\InjectConfiguration(path: 'gravatar')]
    protected array $gravatarSettings;

    public function getCurrentUser(): ?User
    {
        return $this->userService->getCurrentUser();
    }

    public function getUploadedAvatar(User $user): ?PersistentResource
    {
        $identifier = $user->getPreferences()?->get(self::PREFERENCE_KEY);
        if (!is_string($identifier) || $identifier === '') {
            return null;
        }
        $resource = $this->persistenceManager->getObjectByIdentifier($identifier, PersistentResource::class);

        // The preference value is not trusted: a user can set preferences through the Neos API.
        // Only a photo that this package stored for this very user is served or deleted.
        if (
            !$resource instanceof PersistentResource
            || $resource->getCollectionName() !== self::COLLECTION
            || pathinfo($resource->getFilename(), PATHINFO_FILENAME) !== $this->filenameFor($user)
        ) {
            return null;
        }

        return $resource;
    }

    /**
     * The URI of the user's photo: the uploaded one, else Gravatar (when enabled), else null.
     */
    public function getAvatarUri(User $user): ?string
    {
        $resource = $this->getUploadedAvatar($user);
        if ($resource !== null) {
            $uri = $this->resourceManager->getPublicPersistentResourceUri($resource);
            if (is_string($uri)) {
                return $uri;
            }
        }

        return $this->getGravatarUri($user);
    }

    public function isGravatarEnabled(): bool
    {
        return (bool)($this->gravatarSettings['enabled'] ?? false);
    }

    public function getGravatarUri(User $user): ?string
    {
        if (!$this->isGravatarEnabled()) {
            return null;
        }
        $email = $this->getEmailAddress($user);
        if ($email === null) {
            return null;
        }

        return sprintf(
            'https://gravatar.com/avatar/%s?%s',
            hash('sha256', strtolower(trim($email))),
            http_build_query([
                's' => $this->size,
                'd' => (string)($this->gravatarSettings['default'] ?? '404'),
                'r' => (string)($this->gravatarSettings['rating'] ?? 'g'),
            ])
        );
    }

    /**
     * The e-mail address for Gravatar, in this order: the primary electronic address when it is an
     * e-mail address, the first other e-mail address of the user, the account identifier when it
     * is an e-mail address.
     */
    public function getEmailAddress(User $user): ?string
    {
        $candidates = [$user->getPrimaryElectronicAddress()];
        foreach ($user->getElectronicAddresses() as $electronicAddress) {
            $candidates[] = $electronicAddress;
        }
        foreach ($candidates as $electronicAddress) {
            if (
                $electronicAddress !== null
                && $electronicAddress->getType() === 'Email'
                && filter_var($electronicAddress->getIdentifier(), FILTER_VALIDATE_EMAIL)
            ) {
                return $electronicAddress->getIdentifier();
            }
        }
        foreach ($user->getAccounts() as $account) {
            if (filter_var($account->getAccountIdentifier(), FILTER_VALIDATE_EMAIL)) {
                return $account->getAccountIdentifier();
            }
        }

        return null;
    }

    /**
     * Replaces the user's photo with a square version of the uploaded image.
     *
     * The uploaded original is deleted afterwards; only the square copy is kept.
     *
     * @throws \InvalidArgumentException when the upload is not a supported image
     */
    public function storeAvatar(User $user, PersistentResource $upload): void
    {
        try {
            $this->assertSupportedUpload($upload);
            $content = $this->makeSquare($upload);
        } finally {
            $this->resourceManager->deleteResource($upload);
        }

        $resource = $this->resourceManager->importResourceFromContent(
            $content,
            $this->filenameFor($user) . '.' . $this->extensionFor($upload->getMediaType()),
            self::COLLECTION
        );
        $previous = $this->getUploadedAvatar($user);
        $this->preferencesOf($user)->set(self::PREFERENCE_KEY, $this->persistenceManager->getIdentifierByObject($resource));
        $this->userService->updateUser($user);
        if ($previous !== null) {
            $this->resourceManager->deleteResource($previous);
        }
    }

    public function removeAvatar(User $user): void
    {
        $previous = $this->getUploadedAvatar($user);
        $this->preferencesOf($user)->set(self::PREFERENCE_KEY, null);
        $this->userService->updateUser($user);
        if ($previous !== null) {
            $this->resourceManager->deleteResource($previous);
        }
    }

    private function assertSupportedUpload(PersistentResource $upload): void
    {
        if (!in_array($upload->getMediaType(), self::ALLOWED_MEDIA_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported file type "%s".', $upload->getMediaType()), 1795680001);
        }
        if ($upload->getFileSize() > $this->maximumUploadSize) {
            throw new \InvalidArgumentException('The file is too large.', 1795680002);
        }
        // Refuse decompression bombs before Imagine decodes the whole image.
        $size = @getimagesize($upload->createTemporaryLocalCopy());
        // The media type above comes from the file name; the image type is read from the content.
        if ($size === false || !in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $size[0] < 1 || $size[1] < 1) {
            throw new \InvalidArgumentException('The uploaded file is not a readable image.', 1795680005);
        }
        if ($size[0] * $size[1] > self::MAXIMUM_PIXELS) {
            throw new \InvalidArgumentException('The image has too many pixels.', 1795680006);
        }
    }

    private function filenameFor(User $user): string
    {
        return 'avatar-' . $this->persistenceManager->getIdentifierByObject($user);
    }

    private function makeSquare(PersistentResource $upload): string
    {
        $stream = $upload->getStream();
        if (!is_resource($stream)) {
            throw new \InvalidArgumentException('The uploaded file cannot be read.', 1795680003);
        }
        try {
            $image = $this->imagine->read($stream);
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException('The uploaded file is not a readable image.', 1795680004, $exception);
        } finally {
            fclose($stream);
        }

        try {
            // Phone photos are often stored sideways with an EXIF orientation.
            $image = (new Autorotate())->apply($image);
        } catch (\Throwable) {
            // No orientation data: keep the image as it is.
        }

        $square = $image->thumbnail(new Box($this->size, $this->size), ManipulatorInterface::THUMBNAIL_OUTBOUND);
        $format = $this->extensionFor($upload->getMediaType());

        return $square->get($format, $format === 'jpg' ? ['jpeg_quality' => 85] : []);
    }

    private function extensionFor(string $mediaType): string
    {
        return $mediaType === 'image/png' ? 'png' : 'jpg';
    }

    private function preferencesOf(User $user): UserPreferences
    {
        $preferences = $user->getPreferences();
        if ($preferences === null) {
            $preferences = new UserPreferences();
            $user->setPreferences($preferences);
        }

        return $preferences;
    }
}
