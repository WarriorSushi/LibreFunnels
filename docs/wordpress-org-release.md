# WordPress.org Release Plan

Official references:
- https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/
- https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

## Requirements
- GPLv2-or-later compatible.
- Complete plugin at submission.
- No trialware.
- No paid feature locks.
- Human-readable code.
- No trademark misuse.
- No unsolicited tracking.
- No external executable code loading.
- No public-facing credit links unless opt-in.
- Proper sanitization, escaping, nonces, and capabilities.

## Release Process
1. Build and test locally.
2. Package complete plugin ZIP.
3. Submit to WordPress.org.
4. Address review feedback.
5. Receive SVN repository.
6. Push code to `trunk/`.
7. Tag release under `tags/x.y.z`.
8. Keep Git as development source and SVN as release channel.
