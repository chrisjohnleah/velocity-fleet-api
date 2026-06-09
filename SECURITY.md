# Security Policy

## Supported versions

This project is in early development. Security fixes are applied to the latest release on the `main` branch.

## Reporting a vulnerability

Please **do not** report security vulnerabilities through public GitHub issues.

Instead, email **christopher.leah@happywebs.co.uk** with:

- a description of the vulnerability and its impact,
- steps to reproduce (a proof of concept if possible),
- any suggested remediation.

You'll receive an acknowledgement as soon as possible, and a fix or mitigation plan once the report has been triaged. Please give a reasonable window to address the issue before any public disclosure.

## Scope notes

This SDK handles API tokens and OAuth2 access/refresh tokens. When reporting, please be mindful of anything touching token storage, the refresh-token exchange, or the handling of the `Authorization` header.
