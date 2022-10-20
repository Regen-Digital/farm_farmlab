# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## 1.0.0-beta2 2022-10-20

### Added

- Add dependency on guzzlehttp/guzzle ^7.4.5.

### Fixed

- Update FarmLabClient::requestAsync declaration to be compatible with Client::requestAsync. [#6](https://github.com/paul121/farm_farmlab/issues/6)

## 1.0.0-beta1 2022-09-08

### Added

- Add managed role permissions for `use farmlab` and `connect farmlab`.

### Changed

- Save the current user id to state and enforce that only this user can connect farms after authorizing farmlab.

## 1.0.0-alpha2 2022-08-31

### Changed

- Add delay and retry attempt when fetching account during grant flow.
- Change FarmLabClientInterface::getAccount() to only return the connected account.

## 1.0.0-alpha1 2022-08-30

Initial alpha release. Should only be used for testing.
