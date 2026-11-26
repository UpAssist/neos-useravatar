<?php

declare(strict_types=1);

namespace UpAssist\Neos\UserAvatar\Controller\Module;

use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Neos\Controller\Module\AbstractModuleController;
use UpAssist\Neos\UserAvatar\Service\UserAvatarService;

/**
 * Backend module "User > Profile photo": upload, replace or remove your own photo.
 */
class UserAvatarController extends AbstractModuleController
{
    #[Flow\Inject]
    protected UserAvatarService $userAvatarService;

    #[Flow\Inject]
    protected Translator $translator;

    public function indexAction(): void
    {
        $user = $this->userAvatarService->getCurrentUser();
        $hasUpload = $user !== null && $this->userAvatarService->getUploadedAvatar($user) !== null;
        $this->view->assignMultiple([
            'user' => $user,
            'avatarUri' => $user === null ? null : $this->userAvatarService->getAvatarUri($user),
            'hasUpload' => $hasUpload,
            'gravatarEnabled' => $this->userAvatarService->isGravatarEnabled(),
            'gravatarEmail' => $user === null ? null : $this->userAvatarService->getEmailAddress($user),
            'acceptedTypes' => implode(',', UserAvatarService::ALLOWED_MEDIA_TYPES),
        ]);
    }

    public function uploadAction(?PersistentResource $avatar = null): void
    {
        $user = $this->userAvatarService->getCurrentUser();
        if ($user === null || $avatar === null) {
            $this->addFlashMessage($this->translate('message.noFile'), '', Message::SEVERITY_WARNING);
            $this->redirect('index');
        }
        try {
            $this->userAvatarService->storeAvatar($user, $avatar);
            $this->addFlashMessage($this->translate('message.saved'));
        } catch (\InvalidArgumentException) {
            $this->addFlashMessage($this->translate('message.invalidFile'), '', Message::SEVERITY_ERROR);
        }
        $this->redirect('index');
    }

    public function removeAction(): void
    {
        $user = $this->userAvatarService->getCurrentUser();
        if ($user !== null) {
            $this->userAvatarService->removeAvatar($user);
            $this->addFlashMessage($this->translate('message.removed'));
        }
        $this->redirect('index');
    }

    private function translate(string $id): string
    {
        return $this->translator->translateById($id, [], null, null, 'Main', 'UpAssist.Neos.UserAvatar') ?? $id;
    }
}
