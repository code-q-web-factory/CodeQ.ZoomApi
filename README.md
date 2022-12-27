[![Latest Stable Version](https://poser.pugx.org/codeq/zoom-api/v/stable)](https://packagist.org/packages/codeq/zoom-api)
[![License](https://poser.pugx.org/codeq/zoom-api/license)](LICENSE)

# CodeQ.ZoomApi

This package gets upcoming meetings and meeting recordings from Zoom. 
You can use the Eel helper to show these on your website

*The development and the public-releases of this package are generously sponsored by [Code Q Web Factory](http://codeq.at).*

## Installation

CodeQ.ZoomApi is available via packagist run `composer require codeq/zoom-api`.
We use semantic versioning so every breaking change will increase the major-version number.

## Usage

Create a Zoom App for your login:

```
CodeQ:
  ZoomApi:
    auth:
      apiKey: ''
      apiSecret: ''
```

Then use the Eel helper:

```
CodeQ.ZoomApi.getUpcomingMeetings()
CodeQ.ZoomApi.getRecordings('2021-01-01', 'now')
CodeQ.ZoomApi.getRecordings(Date.create('2021-01-01'), Date.now())
```

Be aware, that this helper currently does not implement caching.

## Performance and Caching

Beware, that the package does not cache the requests by default. Thus, using these Eel helpers on
heavily frequented pages can lead to rate limit issues with the Zoom API. This package provides
a request cache to tackle that issue. 

By default, the cache is disabled. To enable the cache, configure the lifetime at your convenience:

```yaml
CodeQ_ZoomApi_Requests:
  backendOptions:
    defaultLifetime: 600 # e.g. 60 seconds * 10 minutes = 600 seconds
```

Of course, you can also switch to a different cache backend at your convenience.
