# Contributing to Ktab-L7anout

Ktab-L7anout helps families in Morocco settle verified grocery debts. Contributions are welcome in French, Arabic, or English. You can help with bug fixes, accessibility, translations, tests, and documentation.

## Open an issue

- [Report a bug](https://github.com/mokhtargalaxy06-ops/Ktab-L7anout/issues/new?template=bug_report.md). Describe what you expected, what happened, and how to reproduce it. Include the language, the account role, and the environment if relevant.
- [Suggest an improvement](https://github.com/mokhtargalaxy06-ops/Ktab-L7anout/issues/new?template=feature_request.md). Describe who would benefit and what they should be able to do.

Search [existing issues](https://github.com/mokhtargalaxy06-ops/Ktab-L7anout/issues) before opening a new one. Use fictitious data and redact identifying information from screenshots or logs. Report security concerns privately to the maintainer through the [README contact section](README.md#contact).

## Submit a pull request

1. Fork the repository, clone your fork, and create a branch from `main`.
2. Read [AGENTS.md](AGENTS.md) and the relevant [project documentation](docs/architecture.md). Follow the [local setup guide](README.md#local-setup).
3. Make a focused change and update the relevant documentation or tests. For interface changes, check French and Arabic, including right-to-left layout. For financial changes, cover each affected payment or confirmation state and use simulated payments or fictitious records.
4. Run the checks below for the parts you changed.
5. Open a pull request from your branch to `main`. Summarize the change, list the checks you ran, and link the issue if there is one.

### Backend checks

Run from `backend/`.

```sh
php artisan test
php vendor/bin/pint --test
```

### Frontend checks

Run from `frontend/`.

```sh
npm test
npm run lint
npm run build
```

On Windows PowerShell, use `npm.cmd` if the npm PowerShell shim is blocked.
