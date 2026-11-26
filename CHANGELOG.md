# Changelog

All notable changes to this project are documented in this file. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/).

## Unreleased

### Added

- A "Profile photo" block on the User Settings page to upload, replace or remove your own photo
  (JPEG or PNG, at most 40 megapixels). The photo is cropped to a 256 × 256 square (EXIF orientation applied) and stored as a resource; its
  identifier is kept in the user's preferences, so there is no database migration.
- `/neos/user-avatar`: redirects to the photo of the logged-in backend user, or answers 404;
  `/neos/user-avatar/settings`, `/upload` and `/remove` for the User Settings block.
- Every logged-in backend user can set a photo, also with a custom role that does not extend
  `Neos.Neos:AbstractEditor` (privilege target `UpAssist.Neos.UserAvatar:Own`, deny it per role).
- Optional Gravatar fallback (`UpAssist.Neos.UserAvatar.gravatar.enabled`, off by default), using
  the primary e-mail address, else any other e-mail address, else an e-mail account name.
- The photo replaces the initials (Neos 9) or the user icon (Neos 8) in the user menu of the
  Neos UI and of the backend modules.
