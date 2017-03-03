# Docs for `nailsapp/driver-cdn-awslocal`
> Documentation is a WIP.


Enable this driver by setting the `APP_CDN_DRIVER` constant to `nailsapp/driver-cdn-awslocal`.


## Configuration

The following constants can must be defined in order to correctly configure the driver:

| Constant                                       | Description                                                                        | Default |
|------------------------------------------------|------------------------------------------------------------------------------------|---------|
| `APP_CDN_DRIVER_AWS_ACCESS_ID`                 | The access key for an IAM user whi ahs read/write permission to S3 bucket          | `null`  |
| `APP_CDN_DRIVER_AWS_ACCESS_SECRET`             | The access secret for an IAM user whi ahs read/write permission to S3 bucket       | `null`  |
| `APP_CDN_DRIVER_AWS_S3_BUCKET_{{ENVIRONMENT}}` | The S3 bucket to use for the environment specified in `{{ENVIRONMENT}}`            | `null`  |


Optionally, you can further configure the driver with the following options:

| Constant                                   | Description                                                     | Default                               |
|--------------------------------------------|-----------------------------------------------------------------|---------------------------------------|
| `APP_CDN_DRIVER_AWS_URI_SERVE`             | The URI to serve objects from.                                  | `http://{{bucket}}.s3.amazonaws.com`  |
| `APP_CDN_DRIVER_AWS_URI_SERVE_SECURE`      | The secure URI to serve objects from.                           | `https://{{bucket}}.s3.amazonaws.com` |
| `APP_CDN_DRIVER_AWS_URI_PROCESS`           | The URI to serve objects from which will be transformed.        | `site_url('cdn')`                     |
| `APP_CDN_DRIVER_AWS_URI_PROCESS_SECURE`    | The secure URI to serve objects from which will be transformed. | `site_url('cdn', true)`               |

