# UpAssist Neos User Avatar

A profile photo for Neos backend users. The photo replaces the initials (Neos 9, in the drawer
at the bottom left) or the user icon (Neos 8, top right) in the user menu of the Neos UI and of
the backend modules.

- Every editor uploads their own photo in **User > Profile photo**.
- Optionally, users without an uploaded photo get their [Gravatar](https://gravatar.com).
- Without a photo, Neos shows the initials or icon as before.

## Compatibility

| Version | Neos |
| --- | --- |
| unreleased | 8.3, 8.4, 9.0, 9.1, 9.2 |

## Installation

```bash
composer require upassist/neos-useravatar
```

No database migration is needed.

## Configuration

```yaml
UpAssist:
  Neos:
    UserAvatar:
      size: 256                    # width and height of the stored photo, in pixels
      maximumUploadSize: 10485760  # bytes
      gravatar:
        enabled: false
        default: '404'             # '404' keeps the Neos initials; or 'mp', 'identicon', ...
        rating: 'g'
```

### Gravatar

Gravatar is off by default. With `gravatar.enabled: true`, a user without an uploaded photo gets
the Gravatar of their primary e-mail address, or of their account name when that is an e-mail
address. The hash is SHA-256.

The browser of the editor then loads the photo from gravatar.com, so Gravatar (Automattic) sees
the editor's IP address and the hash of the e-mail address. Decide whether that is acceptable
for your editors before you enable it.

## How it works

- Uploads are JPEG or PNG (GIF and WebP can hold many frames), at most 10 MB and 40 megapixels.
- The uploaded photo is cropped to a square from the centre, rotated by its EXIF orientation and
  stored as a Flow resource in its own collection, `upassistUserAvatar` (same storage and target
  as `persistent`). It is not a Media asset, so it does
  not show up in the Media module. Its identifier is stored in the user's preferences
  (`UpAssist.Neos.UserAvatar.resource`). Because users can set their own preferences, the package
  only serves or deletes a resource from its own collection whose file name carries that user's
  identifier.
- `GET /neos/user-avatar` answers for the logged-in backend user: a redirect to the uploaded
  photo, else to Gravatar (when enabled), else 404.
- In the Neos UI, a plugin wraps the user menu container (`Drawer/Bottom/UserDropDown` in Neos 9,
  `PrimaryToolbar/Right/UserDropDown` in Neos 8). The Neos component stays in place, so logout,
  user settings and "restore user" keep working. In the backend modules a small script does the
  same for the Fluid user menu.
- The privileges are granted to `Neos.Neos:AbstractEditor`, so every backend user can set their
  own photo.

## Development

The Neos UI plugin is built with esbuild as described in the
[Neos UI extensibility guideline](https://github.com/neos/neos-ui/blob/9.2/packages/neos-ui-extensibility/README.md).
One build serves Neos UI 8.3 up to 9.x. The built `Resources/Public/Plugin/Plugin.js` and
`Plugin.css` are committed, so installing the package needs no Node.

```bash
cd Resources/Private/Scripts
yarn install --frozen-lockfile
yarn build   # or: yarn watch
```

Commit the rebuilt files in `Resources/Public` together with the source change; CI checks that
they match.

## License

MIT
