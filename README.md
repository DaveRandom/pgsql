# amp/pgsql

[![Build Status](https://img.shields.io/travis/amphp/pgsql/master.svg?style=flat-square)](https://travis-ci.org/amphp/amp)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/pgsql/master.svg?style=flat-square)](https://coveralls.io/github/amphp/amp?branch=master)
![Unstable](https://img.shields.io/badge/dev-master-yellow.svg?style=flat-square)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`amphp/pgsql` is a non-blocking PostgreSQL library built on the [amp concurrency framework](https://github.com/amphp/amp).

> **WARNING**
> 
> This library is currently unstable. The API is volatile and subject to change. Use at your own risk ... you cowboy.

**Required PHP Version**

- PHP 7+

**Required PHP Extensions**

- `ext/pgsql`

This library relies on the pgsql extension shipped as part of standard PHP installs. No additional configuration is needed for most scenarios.

**Installation**

```bash
$ composer require amphp/pgsql
```
