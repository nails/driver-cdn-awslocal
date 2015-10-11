<?php

namespace Nails\Cdn\Driver;

class Awslocal implements \Nails\Cdn\Interfaces\Driver
{
    private $oCdn;
    private $sBucketUrl;
    private $sS3Bucket;
    private $oS3;

    // --------------------------------------------------------------------------

    /**
     * Constructor
     */
    public function __construct($oCdn)
    {
        $this->oCdn = $oCdn;

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
        $this->oS3 = \Aws\S3\S3Client::factory(array(
            'key'    => DEPLOY_CDN_DRIVER_AWS_IAM_ACCESS_ID,
            'secret' => DEPLOY_CDN_DRIVER_AWS_IAM_ACCESS_SECRET,
        ));

        // --------------------------------------------------------------------------

        //  Set the bucket we're using
        $this->sS3Bucket = DEPLOY_CDN_DRIVER_AWS_S3_BUCKET;

        // --------------------------------------------------------------------------

        //  Finally, define the bucket endpoint/url, in case they change it.
        $this->sBucketUrl = '.s3.amazonaws.com/';
    }

    /**
     * OBJECT METHODS
     */

    /**
     * Creates a new object
     * @param  stdClass $oData Data to create the object with
     * @return boolean
     */
    public function objectCreate($oData)
    {
        $sBucket       = ! empty($oData->bucket->slug) ? $oData->bucket->slug : '';
        $sFilenameOrig = ! empty($oData->filename) ? $oData->filename : '';

        $sFilename  = strtolower(substr($sFilenameOrig, 0, strrpos($sFilenameOrig, '.')));
        $mSextension = strtolower(substr($sFilenameOrig, strrpos($sFilenameOrig, '.')));

        $sSource = ! empty($oData->file) ? $oData->file : '';
        $sMime   = ! empty($oData->mime) ? $oData->mime : '';
        $sName   = ! empty($oData->name) ? $oData->name : 'file' . $mSextension;

        // --------------------------------------------------------------------------

        try {

            $this->oS3->putObject(array(
                'Bucket'      => $this->sS3Bucket,
                'Key'         => $sBucket . '/' . $sFilename . $mSextension,
                'SourceFile'  => $sSource,
                'ContentType' => $sMime,
                'ACL'         => 'public-read'
            ));

            /**
             * Now try to duplicate the file and set the appropriate meta tag so there's
             * a downloadable version
             */

            try {

                $this->oS3->copyObject(array(
                    'Bucket'             => $this->sS3Bucket,
                    'CopySource'         => $this->sS3Bucket . '/' . $sBucket . '/' . $sFilename . $mSextension,
                    'Key'                => $sBucket . '/' . $sFilename . '-download' . $mSextension,
                    'ContentType'        => 'application/octet-stream',
                    'ContentDisposition' => 'attachment; filename="' . str_replace('"', '', $sName) . '" ',
                    'MetadataDirective'  => 'REPLACE',
                    'ACL'                => 'public-read'
                ));

                return true;

            } catch (\Exception $oE) {

                $this->oCdn->set_error('AWS-SDK EXCEPTION: ' . get_class($oE) . ': ' . $oE->getMessage());
                return false;
            }

        } catch (\Exception $oE) {

            $this->oCdn->set_error('AWS-SDK EXCEPTION: ' . get_class($oE) . ': ' . $oE->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether an object exists or not
     * @param  string $sFilename The object's filename
     * @param  string $sBucket   The bucket's slug
     * @return boolean
     */
    public function objectExists($sFilename, $sBucket)
    {
        return $this->oS3->doesObjectExist($sBucket, $sFilename);
    }

    // --------------------------------------------------------------------------

    /**
     * Destroys (permenantly deletes) an object
     * @param  string $sObject The object's filename
     * @param  string $sBucket The bucket's slug
     * @return boolean
     */
    public function objectDestroy($sObject, $sBucket)
    {
        try {

            $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
            $mSextension = strtolower(substr($sObject, strrpos($sObject, '.')));

            $aOptions              = array();
            $aOptions['Bucket']    = $this->sS3Bucket;
            $aOptions['Objects']   = array();
            $aOptions['Objects'][] = array('Key' => $sBucket . '/' . $sFilename . $mSextension);
            $aOptions['Objects'][] = array('Key' => $sBucket . '/' . $sFilename . '-download' . $mSextension);

            $this->oS3->deleteObjects($aOptions);
            return true;

        } catch (\Exception $oE) {

            $this->oCdn->set_error('AWS-SDK EXCEPTION: ' . get_class($oE) . ': ' . $oE->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a local path for an object
     * @param  string $sBucket   The bucket's slug
     * @param  string $sFilename The filename
     * @return mixed             String on success, false on failure
     */
    public function objectLocalPath($sBucket, $sFilename)
    {
        //  Do we have the original sourcefile?
        $mSextension = strtolower(substr($sFilename, strrpos($sFilename, '.')));
        $sFilename  = strtolower(substr($sFilename, 0, strrpos($sFilename, '.')));
        $sSrcFile   = DEPLOY_CACHE_DIR . $sBucket . '-' . $sFilename . '-SRC' . $mSextension;

        //  Check filesystem for sourcefile
        if (file_exists($sSrcFile)) {

            //  Yup, it's there, so use it
            return $sSrcFile;

        } else {

            //  Doesn't exist, attempt to fetch from S3
            try {

                $this->oS3->getObject(array(
                    'Bucket' => $this->sS3Bucket,
                    'Key'    => $sBucket . '/' . $sFilename . $mSextension,
                    'SaveAs' => $sSrcFile
                ));

                return $sSrcFile;

            } catch (\Aws\S3\Exception\S3Exception $oE) {

                //  Clean up
                if (file_exists($sSrcFile)) {

                    unlink($sSrcFile);
                }

                //  Note the error
                $this->oCdn->set_error('AWS-SDK EXCEPTION: ' . get_class($oE) . ': ' . $oE->getMessage());

                return false;
            }
        }
    }

    /**
     * BUCKET METHODS
     */

    /**
     * Creates a new bucket
     * @param  string  $sBucket The bucket's slug
     * @return boolean
     */
    public function bucketCreate($sBucket)
    {
        //  Attempt to create a 'folder' object on S3
        if (!$this->oS3->doesObjectExist($this->sS3Bucket, $sBucket . '/')) {

            try {

                $this->oS3->putObject(array(
                    'Bucket' => $this->sS3Bucket,
                    'Key'    => $sBucket . '/',
                    'Body'   => ''
                ));

                return true;

            } catch (\Exception $oE) {

                $this->oCdn->set_error('AWS-SDK ERROR: ' . $oE->getMessage());
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
     * @param  string  $sBucket The bucket's slug
     * @return boolean
     */
    public function bucketDestroy($sBucket)
    {
        try {

            $this->oS3->deleteMatchingObjects($this->sS3Bucket, $sBucket . '/');
            return true;

        } catch (\Exception $oE) {

            $this->oCdn->set_error('AWS-SDK ERROR: ' . $oE->getMessage());
            return false;
        }
    }

    /**
     * URL GENERATOR METHODS
     */

    /**
     * Generates the correct URL for serving a file
     * @param  string  $sObject        The object to serve
     * @param  string  $sBucket        The bucket to serve from
     * @param  boolean $bForceDownload Whether to force a download
     * @return string
     */
    public function urlServe($sObject, $sBucket, $bForceDownload = false)
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_SERVING;
        $sUrl .= $sBucket . '/';

        if ($bForceDownload) {

            /**
             * If we're forcing the download we need to reference a slightly different file.
             * On upload two instances were created, the "normal" streaming type one and
             * another with the appropriate content-types set so that the browser downloads
             * as oppossed to renders it
             */

            $sFilename  = strtolower(substr($sObject, 0, strrpos($sObject, '.')));
            $mSextension = strtolower(substr($sObject, strrpos($sObject, '.')));

            $sUrl .= $sFilename;
            $sUrl .= '-download';
            $sUrl .= $mSextension;

        } else {

            //  If we're not forcing the download we can serve straight out of S3
            $sUrl .= $sObject;
        }

        return $this->urlMakeSecure($sUrl, false);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'serve' URLs
     * @param  boolean $bForceDownload Whetehr or not to force download
     * @return string
     */
    public function urlServeScheme($bForceDownload = false)
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_SERVING;
        $sUrl .= '{{bucket}}/';

        if ($bForceDownload) {

            /**
             * If we're forcing the download we need to reference a slightly different file.
             * On upload two instances were created, the "normal" streaming type one and
             * another with the appropriate content-types set so that the browser downloads
             * as oppossed to renders it
             */

            $sUrl .= '{{filename}}-download{{extension}}';

        } else {

            //  If we're not forcing the download we can serve straight out of S3
            $sUrl .= '{{filename}}{{extension}}';
        }

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a URL for serving zipped objects
     * @param  string $sObjectIds A comma seperated list of object IDs
     * @param  string $sHash      The security hash
     * @param  string $sFilename  The filename to give the zip file
     * @return string
     */
    public function urlServeZipped($sObjectIds, $sHash, $sFilename)
    {
        $sFilename = $sFilename ? '/' . urlencode($sFilename) : '';

        $sUrl = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'zip/' . $sObjectIds . '/' . $sHash . $sFilename;

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'zipped' urls
     * @return  string
     */
    public function urlServeZippedScheme()
    {
        $sUrl = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'zip/{{ids}}/{{hash}}/{{filename}}';
        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the crop utility
     * @param   string  $sBucket The bucket which the image resides in
     * @param   string  $sObject The filename of the image we're cropping
     * @param   integer $iWidth  The width of the cropped image
     * @param   integer $iHeight The height of the cropped image
     * @return  string
     */
    public function urlCrop($sObject, $sBucket, $iWidth, $iHeight)
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'crop/';
        $sUrl .= $iWidth . '/' . $iHeight . '/';
        $sUrl .= $sBucket . '/';
        $sUrl .= $sObject;

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'crop' urls
     * @return  string
     */
    public function urlCropScheme()
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
        $sUrl .= 'crop/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}';

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the scale utility
     * @param   string  $sBucket The bucket which the image resides in
     * @param   string  $sObject The filename of the image we're 'scaling'
     * @param   integer $iWidth  The width of the scaled image
     * @param   integer $iHeight The height of the scaled image
     * @return  string
     */
    public function urlScale($sObject, $sBucket, $iWidth, $iHeight)
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'scale/';
        $sUrl .= $iWidth . '/' . $iHeight . '/';
        $sUrl .= $sBucket . '/';
        $sUrl .= $sObject;

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'scale' urls
     * @return  string
     */
    public function urlScaleScheme()
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
        $sUrl .= 'scale/{{width}}/{{height}}/{{bucket}}/{{filename}}{{extension}}';

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for using the placeholder utility
     * @param   integer $iWidth  The width of the placeholder
     * @param   integer $iHeight The height of the placeholder
     * @param   integer $iBorder The width of the border round the placeholder
     * @return  string
     */
    public function urlPlaceholder($iWidth, $iHeight, $iBorder = 0)
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'placeholder/';
        $sUrl .= $iWidth . '/' . $iHeight . '/' . $iBorder;

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'placeholder' urls
     * @return  string
     */
    public function urlPlaceholderScheme()
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
        $sUrl .= 'placeholder/{{width}}/{{height}}/{{border}}';

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates the correct URL for a blank avatar
     * @param  integer        $iWidth  The width fo the avatar
     * @param  integer        $iHeight The height of the avatar§
     * @param  string|integer $mSex    What gender the avatar should represent
     * @return string
     */
    public function urlBlankAvatar($iWidth, $iHeight, $mSex = '')
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING . 'blank_avatar/';
        $sUrl .= $iWidth . '/' . $iHeight . '/' . $mSex;

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'blank_avatar' urls
     * @return  string
     */
    public function urlBlankAvatarScheme()
    {
        $sUrl  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
        $sUrl .= 'blank_avatar/{{width}}/{{height}}/{{sex}}';

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a properly hashed expiring url
     * @param  string  $sBucket        The bucket which the image resides in
     * @param  string  $sObject        The object to be served
     * @param  integer $iExpires       The length of time the URL should be valid for, in seconds
     * @param  boolean $bForceDownload Whether to force a download
     * @return string
     */
    public function urlExpiring($sObject, $sBucket, $iExpires, $bForceDownload = false)
    {
        /**
         * @todo: If CloudFront is configured, then generate a secure url and pass
         * back, if not serve through the processing mechanism. Maybe.
         */

        //  Hash the expiry time
        $sHash  = $sBucket . '|' . $sObject . '|' . $iExpires . '|' . time() . '|';
        $sHash .= md5(time() . $sBucket . $sObject . $iExpires . APP_PRIVATE_KEY);
        $sHash  = get_instance()->encrypt->encode($sHash, APP_PRIVATE_KEY);
        $sHash  = urlencode($sHash);

        $sUrl = 'serve?token=' . $sHash;

        if ($bForceDownload) {

            $sUrl .= '&dl=1';
        }

        $sUrl = site_url($sUrl);

        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the scheme of 'expiring' urls
     * @return  string
     */
    public function urlExpiringScheme()
    {
        $sUrl = site_url('serve?token={{token}}');
        return $this->urlMakeSecure($sUrl);
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a URL and makes it secure if needed
     * @param  string  $sUrl          The URL to secure
     * @param  boolean $bIsProcessing Whether it's a processing type URL
     * @return string
     */
    protected function urlMakeSecure($sUrl, $bIsProcessing = true)
    {
        if (isPageSecure()) {

            //  Make the URL secure
            if ($bIsProcessing) {

                $sSearch  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING;
                $sReplace = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_PROCESSING_SECURE;

            } else {

                $sSearch  = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_SERVING;
                $sReplace = DEPLOY_CDN_DRIVER_AWS_CLOUDFRONT_URL_SERVING_SECURE;
            }

            $sUrl = str_replace($sSearch, $sReplace, $sUrl);
        }

        return $sUrl;
    }
}
