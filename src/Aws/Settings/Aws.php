<?php

namespace Nails\Cdn\Driver\Aws\Settings;

use Nails\Common\Helper\Form;
use Nails\Common\Interfaces;
use Nails\Common\Service\FormValidation;
use Nails\Components\Setting;
use Nails\Environment;
use Nails\Factory;

/**
 * Class Aws
 *
 * @package Nails\Cdn\Driver\Aws\Settings
 */
class Aws implements Interfaces\Component\Settings
{
    const KEY_ACCESS_KEY    = 'access_key';
    const KEY_ACCESS_SECRET = 'access_secret';
    const KEY_CONFIG        = 'config';
    const KEY_BUCKETS       = 'buckets';
    const KEY_URIS          = 'uris';

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return 'CDN: AWS S3';
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getPermissions(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        /** @var Setting $oAccessKey */
        $oAccessKey = Factory::factory('ComponentSetting');
        $oAccessKey
            ->setKey(static::KEY_ACCESS_KEY)
            ->setLabel('Access Key')
            ->setEncrypted(true)
            ->setFieldset('Credentials')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oAccessSecret */
        $oAccessSecret = Factory::factory('ComponentSetting');
        $oAccessSecret
            ->setKey(static::KEY_ACCESS_SECRET)
            ->setType(Form::FIELD_PASSWORD)
            ->setLabel('Access Secret')
            ->setEncrypted(true)
            ->setFieldset('Credentials')
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        /** @var Setting $oConfig */
        $oConfig = Factory::factory('ComponentSetting');
        $oConfig
            ->setKey(static::KEY_CONFIG)
            ->setType(Form::FIELD_TEXTAREA)
            ->setLabel('Config')
            ->setFieldset('Config')
            ->setPlaceholder('Example minimal config:' . PHP_EOL . json_encode([
                Environment::get() => [
                    'region' => 'eu-west-2',
                    'bucket' => 'my-bucket'
                ],
            ], JSON_PRETTY_PRINT))
            ->setInfo(
                <<<EOT
                <p>
                    This field is a JSON object which defines the config for each environment. It should be a key/value
                    object where the key is the environment it applies to and the value is an object with the following
                    properties:
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Default Value</th>
                            <th>Required</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>region</code></td>
                            <td><code>null</code></td>
                            <td>Yes</td>
                            <td>The AWS region to use for this application.</td>
                        </tr>
                        <tr>
                            <td><code>bucket</code></td>
                            <td><code>null</code></td>
                            <td>Yes</td>
                            <td>The bucket in which to store objects, must be in the same region.</td>
                        </tr>
                        <tr>
                            <td><code>serve_uri</code></td>
                            <td><code>https://{{bucket}}.s3.amazonaws.com</code></td>
                            <td>No</td>
                            <td>The URL to serve objects from.</td>
                        </tr>
                        <tr>
                            <td><code>process_uri</code></td>
                            <td><code>/cdn</code></td>
                            <td>No</td>
                            <td>The URL for processing objects (e.g. image resizing).</td>
                        </tr>
                        <tr>
                            <td><code>serve_dist_id</code></td>
                            <td><code>null</code></td>
                            <td>No</td>
                            <td>The ID of the CloudFront distribution being used for serving objects (for invalidations)</td>
                        </tr>
                        <tr>
                            <td><code>process_dist_id</code></td>
                            <td><code>null</code></td>
                            <td>No</td>
                            <td>The ID of the CloudFront distribution being used for processing objects (for invalidations)</td>
                        </tr>
                    </tbody>
                </table>
                EOT
            )
            ->setValidation([
                FormValidation::RULE_REQUIRED,
            ]);

        return [
            $oAccessKey,
            $oAccessSecret,
            $oConfig,
        ];
    }
}
