# Contributing

Thanks for your interest in improving this SDK! Contributions of all sizes are welcome.

## Getting started

```bash
git clone https://github.com/chrisjohnleah/velocity-fleet-api.git
cd velocity-fleet-api
composer install
composer check   # lint + static analysis + tests
```

## Ground rules

- **Tests are required.** Every change must be covered by a Pest test. Tests must not hit the network — fake requests with Saloon's `MockClient`.
- **Keep it green.** `composer check` must pass: Pint (code style), PHPStan at `max`, and the full Pest suite.
- **Match the conventions.** DTOs are `final readonly`, hydrate via `fromArray()` using the typed `MapsAttributes` helpers (never raw array access — PHPStan max forbids it). Requests extend Saloon's `Request`.
- **One focused change per PR.** Update [CHANGELOG.md](CHANGELOG.md) under `Unreleased`.

## Adding an endpoint

1. Add the DTO(s) in `src/Data/` following the existing pattern.
2. Add the request in `src/Requests/` (return a typed DTO from `createDtoFromResponse()`).
3. Expose it via a resource in `src/Resources/` (and a `VelocityFleet` accessor).
4. Add a feature test in `tests/Feature/` mirroring an existing slice.

## Reporting bugs / requesting features

Open an issue with the relevant template. For security vulnerabilities, **do not** open a public issue — see [SECURITY.md](SECURITY.md).
