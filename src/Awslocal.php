<?php

namespace Nails\Cdn\Driver;

class Awslocal implements \Nails\Cdn\Interfaces\Driver
{
    private $cdn;
    private $bucketUrl;
    private $bucket;
    private $s3;

    // --------------------------------------------------------------------------

    /**
     * Constructor
     **/
    public function __construct()
    {
        //  Shortcut to CDN Library (mainly for setting errors)
        $this->cdn =& get_instance()->cdn;

        // --------------------------------------------------------------------------

        //  Load langfile
        get_instance()->lang->load('cdn/cdn_driver_aws_local');

        // --------------------------------------------------------------------------

        /**
         * Check all the constants are defined properly
         * DEPLOY_CDN_DRIVER_AWS_IAM_ACCESS_ID
         * DEPLOY_CDN_DRIVER_AWS_IAM_ACCESS_SECRET
         * DEPLOY_CDN_DRIVER_AWS_S3_BUCKET
         */

        if (!defined('DEPLOY_CDN_DRIVER_AWS_IAM_ACCESS_ID')) {

            //  @todo: Specify correct lang
            show_error(lang('cdn_error_not_configured'));
        }

        if (!defined('DEPLOY_CDN_DRIVER_AWS_IAM_ACCESS_SECRET')) {

            //  @todo: Specify correct lang
            show_error(lang('cdn_error_not_configured'));
        }

        if (!defined('DEPLOY_CDN_DRIVER_AWS_S3_BUCKET')) {

            //  @todo: Specify correct lang
            show_error(lang('cdn_error_not_configured'));
        }

        // --------------------------------------------------------------------------

        //  Instanciate the AWS PHP SDK
        $this->s3 = S3Client::factory(array(
            'key'    => DEPLOY_CDN_DRIVER_AWS_IAM_ACCESS_ID,
            'secret' => DEPLOY_CDN_DRIVER_AWS_IAM_ACCESS_SECRET,
        ));

        // --------------------------------------------------------------------------

        //  Set the bucket we're using
        $this->bucket = DEPLOY_CDN_DRIVER_AWS_S3_BUCKET;

        // --------------------------------------------------------------------------

        //  Finally, define the bucket endpoint/url, in case they change it.
        $this->bucketUrl = '.s3.amazonaws.com/';
    }

    /**
     * OBJECT METHODS
     */

    /**
     * Creates a new object
     * @param  stdClass $data Data to create the object with
     * @return boolean
     **/
    public function object_create($data)
    {
        $bucket       = ! empty($data->bucket->slug) ? $data->bucket->slug : '';
        $filenameOrig = ! empty($data->filename)     ? $data->filename     : '';

        $filename  = strtolower(substr($filenameOrig, 0, strrpos($filenameOrig, '.')));
        $extension = strtolower(substr($filenameOrig, strrpos($filenameOrig, '.')));

        $source = ! empty($data->file) ? $data->file : '';
        $mime   = ! empty($data->mime) ? $data->mime : '';
        $name   = ! empty($data->name) ? $data->name : 'file' . $extension;

        // --------------------------------------------------------------------------

        try {

            $this->s3->putObject(array(
                'Bucket'      => $this->bucket,
                'Key'         => $bucket . '/' . $filename . $extension,
                'SourceFile'  => $source,
                'ContentType' => $mime,
                'ACL'         => 'public-read'
            ));

            /**
             * Now try to duplicate the file and set the appropriate meta tag so there's
             * a downloadable version
             */

            try {

                $this->s3->copyObject(array(
                    'Bucket'             => $this->bucket,
                    'CopySource'         => $this->bucket . '/' . $bucket . '/' . $filename . $extension,
                    'Key'                => $bucket . '/' . $filename . '-download' . $extension,
                    'ContentType'        => 'application/octet-stream',
                    'ContentDisposition' => 'attachment; filename="' . str_replace('"', '', $name) . '" ',
                    'MetadataDirective'  => 'REPLACE',
                    'ACL'                => 'public-read'
                ));

                return true;

            } catch (Exception $e) {

                $this->cdn->set_error('AWS-SDK EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage());
                return false;
            }

        } catch (Exception $e) {

            $this->cdn->set_error('AWS-SDK EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     * @param  string $filename The object's filename
     * @param  string $bucket   The bucket's slug
     * @return boolean
     */
    public function object_exists($filename, $bucket)
    {
        return $this->s3->doesObjectExist($bucket, $filename);
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permenantly deletes) an object
     * @return  boolean
     **/
    public function object_destroy($object, $bucket)
    {
        try {

            $filename  = strtolower(substr($object, 0, strrpos($object, '.')));
            $extension = strtolower(substr($object, strrpos($object, '.')));

            $options              = array();
            $options['Bucket']    = $this->bucket;
            $options['Objects']   = array();
            $options['Objects'][] = array('Key' => $bucket . '/' . $filename . $extension);
            $options['Objects'][] = array('Key' => $bucket . '/' . $filename . '-download' . $extension);

            $this->s3->deleteObjects($options);

            return true;

        } catch (Exception $e) {

            $this->cdn->set_error('AWS-SDK EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     * @param  string $bucket   The bucket's slug
     * @param  string $filename The filename
     * @return mixed            string on success, false on failure
     */
    public function object_local_path($bucket, $filename)
    {
        //  Do we have the original sourcefile?
        $extension = strtolower(substr($filename, strrpos($filename, '.')));
        $filename  = strtolower(substr($filename, 0, strrpos($filename, '.')));
        $srcFile   = DEPLOY_CACHE_DIR . $bucket . '-' . $filename . '-SRC' . $extension;

        //  Check filesystem for sourcefile
        if (file_exists($srcFile)) {

            //  Yup, it's there, so use it
            return $srcFile;

        } else {

            //  Doesn't exist, attempt to fetch from S3
            try {

                $this->s3->getObject(array(
                    'Bucket' => $this->bucket,
                    'Key'    => $bucket . '/' . $filename . $extension,
                    'SaveAs' => $srcFile
                ));

                return $srcFile;

            } catch (\Aws\S3\Exception\S3Exception $e) {

                //  Clean up
                if (file_exists($srcFile)) {

                    unlink($srcFile);
                }

                //  Note the error
                $this->cdn->set_error('AWS-SDK EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage());

                return false;
            }
        }
    }

    /**
     * BUCKET METHODS
     */

    /**
     * Creates a new bucket
     * @param  string $bucket The bucket's slug
     * @return  boolean
     **/
    public function bucket_create($bucket)
    {
        //  Attempt to create a 'folder' object on S3
        if (!$this->s3->doesObjectExist($this->bucket, $bucket . '/')) {

            try {

                $this->s3->putObject(array(
                    'Bucket' => $this->bucket,
                    'Key'    => $bucket . '/',
                    'Body'   => ''
                ));

                return true;

            } catch (Exception $e) {

                $this->cdn->set_error('AWS-SDK ERROR: ' . $e->getMessage());
                return false;
            }

        } else {

            //  Bucket already exists.
            return true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes an existing bucket
     * @param  string $bucket The bucket's slug
     * @return boolean
     */
    public function bucket_destroy($bucket)
    {
        try {

            $this->s3->deleteMatchingObjects($this->bucket, $bucket . '/');

            return true;

        } catch (Exception $e) {

            $this->cdn->set_error('AWS-SDK ERROR: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * URL GENERATOR METHODS
     */

    /**
     * Generates the correct URL for serving up a file
     * @param   string  $bucket The bucket which the image resides in
     * @param   string  $object The filename of the object
     * @return  string
     **/
    public function url_serve($object, $bucket, $forceDownload)
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_SERVING;
        $out .= $bucket . '/';

        if ($forceDownload) {

            /**
             * If we're forcing the download we need to reference a slightly different file.
             * On upload two instances were created, the "normal" streaming type one and
             * another with the appropriate content-types set so that the browser downloads
             * as oppossed to renders it
             */

            $filename  = strtolower(substr($object, 0, strrpos($object, '.')));
            $extension = strtolower(substr($object, strrpos($object, '.')));

            $out .= $filename;
            $out .= '-download';
            $out .= $extension;

        } else {

            //  If we're not forcing the download we can serve straight out of S3
            $out .= $object;
        }

        return $this->urlMakeSecure($out, false);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'serve' URLs
     * @param  boolean $forceDownload Whetehr or not to force download
     * @return string
     */
    public function url_serve_scheme($forceDownload)
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_SERVING;
        $out .= '{{bucket}}/';

        if ($forceDownload) {

            /**
             * If we're forcing the download we need to reference a slightly different file.
             * On upload two instances were created, the "normal" streaming type one and
             * another with the appropriate content-types set so that the browser downloads
             * as oppossed to renders it
             */

            $out .= '{{filename}}-download{{extension}}';

        } else {

            //  If we're not forcing the download we can serve straight out of S3
            $out .= '{{filename}}{{extension}}';
        }

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a URL for serving zipped objects
     * @param  string $objectIds A comma seperated list of object IDs
     * @param  string $hash      The security hash
     * @param  string $filename  The filename ot give the zip file
     * @return string
     */
    public function url_serve_zipped($objectIds, $hash, $filename)
    {
        $filename = $filename ? '/' . urlencode($filename) : '';

        $out = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'zip/' . $objectIds . '/' . $hash . $filename;

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'zipped' urls
     * @return  string
     **/
    public function url_serve_zipped_scheme()
    {
        $out = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'zip/{{ids}}/{{hash}}/{{filename}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the thumb utility
     * @param   string  $bucket The bucket which the image resides in
     * @param   string  $object The filename of the image we're 'thumbing'
     * @param   string  $width  The width of the thumbnail
     * @param   string  $height The height of the thumbnail
     * @return  string
     **/
    public function url_thumb($object, $bucket, $width, $height)
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'thumb/';
        $out .= $width . '/' . $height . '/';
        $out .= $bucket . '/';
        $out .= $object;

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'thumb' urls
     * @return  string
     **/
    public function url_thumb_scheme()
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
        $out .= 'thumb/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the scale utility
     * @param   string  $bucket The bucket which the image resides in
     * @param   string  $object The filename of the image we're 'scaling'
     * @param   string  $width  The width of the scaled image
     * @param   string  $height The height of the scaled image
     * @return  string
     **/
    public function url_scale($object, $bucket, $width, $height)
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'scale/';
        $out .= $width . '/' . $height . '/';
        $out .= $bucket . '/';
        $out .= $object;

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'scale' urls
     * @return  string
     **/
    public function url_scale_scheme()
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
        $out .= 'scale/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the placeholder utility
     * @param   int     $width  The width of the placeholder
     * @param   int     $height The height of the placeholder
     * @param   int     border  The width of the border round the placeholder
     * @return  string
     **/
    public function url_placeholder($width = 100, $height = 100, $border = 0)
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'placeholder/';
        $out .= $width . '/' . $height . '/' . $border;

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'placeholder' urls
     * @return  string
     **/
    public function url_placeholder_scheme()
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
        $out .= 'placeholder/{{width}}/{{height}}/{{border}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for a blank avatar
     * @param   int     $width  The width of the placeholder
     * @param   int     $height The height of the placeholder
     * @param   int     border  The width of the border round the placeholder
     * @return  string
     **/
    public function url_blank_avatar($width = 100, $height = 100, $sex = 'male')
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'blank_avatar/';
        $out .= $width . '/' . $height . '/' . $sex;

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'blank_avatar' urls
     * @return  string
     **/
    public function url_blank_avatar_scheme()
    {
        $out  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
        $out .= 'blank_avatar/{{width}}/{{height}}/{{sex}}';

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     * @param  string  $bucket        The bucket which the image resides in
     * @param  string  $object        The object to be served
     * @param  string  $expires       The length of time the URL should be valid for, in seconds
     * @param  boolean $forceDownload Whether to force a download
     * @return string
     **/
    public function url_expiring($object, $bucket, $expires, $forceDownload = false)
    {
        /**
         * @todo: If cloudfront is configured, then generate a secure url and pass
         * back, if not serve through the processing mechanism. Maybe.
         */

        //  Hash the expiry time
        $hash  = $bucket . '|' . $object . '|' . $expires . '|' . time() . '|';
        $hash .= md5(time() . $bucket . $object . $expires . APP_PRIVATE_KEY);
        $hash  = get_instance()->encrypt->encode($hash, APP_PRIVATE_KEY);
        $hash  = urlencode($hash);

        $out = 'serve?token=' . $hash;

        if ($forceDownload) {

            $out .= '&dl=1';
        }

        $out = site_url($out);

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'expiring' urls
     * @return  string
     **/
    public function url_expiring_scheme()
    {
        $out = site_url('serve?token={{token}}');

        return $this->urlMakeSecure($out);
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a URL and makes it secure if needed
     * @param  string $url The URL to secure
     * @return string
     */
    protected function urlMakeSecure($url, $isProcessing = true)
    {
        if (isPageSecure()) {

            //  Make the URL secure
            if ($isProcessing) {

                $search  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
                $replace = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING_SECURE;

            } else {

                $search  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_SERVING;
                $replace = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_SERVING_SECURE;
            }

            $url = str_replace($search, $replace, $url);
        }

        return $url;
    }
}