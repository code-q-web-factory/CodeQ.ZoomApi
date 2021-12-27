[![Latest Stable Version](https://poser.pugx.org/code-q-web-factory/CodeQ.ZoomApiApi/v/stable)](https://packagist.org/packages/code-q-web-factory/CodeQ.ZoomApiApi)
[![License](https://poser.pugx.org/code-q-web-factory/CodeQ.ZoomApiApi/license)](LICENSE)

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
