# AWS Driver for Nails CDN Module

![license](https://img.shields.io/badge/license-MIT-green.svg)
[![CircleCI branch](https://img.shields.io/circleci/project/github/nails/driver-cdn-awslocal.svg)](https://circleci.com/gh/nails/driver-cdn-awslocal)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nails/driver-cdn-awslocal/badges/quality-score.png)](https://scrutinizer-ci.com/g/nails/driver-cdn-awslocal)
[![Join the chat on Slack!](https://now-examples-slackin-rayibnpwqe.now.sh/badge.svg)](https://nails-app.slack.com/shared_invite/MTg1NDcyNjI0ODcxLTE0OTUwMzA1NTYtYTZhZjc5YjExMQ)

This is the AWS driver for the Nails CDN module, it allows the CDN to use AWS S3 to store content.


## Installing

    composer require nails/driver-cdn-awslocal


## Configure

The driver can be enabled and configured via the admin interface.


## CloudFront

CloudFront can be configured to sit between the user and both the S3 bucket and the transformation endpoint.

> @todo - write up instructions for creating these instances



