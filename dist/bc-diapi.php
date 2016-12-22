<?php
/**
 * Brightcove PHP Dynamic Ingest API Wrapper 0.1.0 (October 2016).
 *
 * REFERENCES:
 *	 Source: http://github.com/brightcoveos
 *
 * AUTHORS:
 *	 Robert Crooks <rcrooks@brightcove.com>
 *
 * CONTRIBUTORS:
 *
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the “Software”),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, alter, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to
 * whom the Software is furnished to do so, subject to the following conditions:
 *
 * 1. The permission granted herein does not extend to commercial use of
 * the Software by entities primarily engaged in providing online video and
 * related services.
 *
 * 2. THE SOFTWARE IS PROVIDED "AS IS", WITHOUT ANY WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, SUITABILITY, TITLE,
 * NONINFRINGEMENT, OR THAT THE SOFTWARE WILL BE ERROR FREE. IN NO EVENT
 * SHALL THE AUTHORS, CONTRIBUTORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY WHATSOEVER, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH
 * THE SOFTWARE OR THE USE, INABILITY TO USE, OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * 3. NONE OF THE AUTHORS, CONTRIBUTORS, NOR BRIGHTCOVE SHALL BE RESPONSIBLE
 * IN ANY MANNER FOR USE OF THE SOFTWARE.  THE SOFTWARE IS PROVIDED FOR YOUR
 * CONVENIENCE AND ANY USE IS SOLELY AT YOUR OWN RISK.  NO MAINTENANCE AND/OR
 * SUPPORT OF ANY KIND IS PROVIDED FOR THE SOFTWARE.
 */

// AWS SDK (for push ingests)
require 'vendor/aws-autoloader.php';

use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;


class BCDIAPI
{
    const ERROR_INVALID_JSON_ACCOUNT_DATA = 1;
    const ERROR_ACCOUNT_ID_NOT_PROVIDED = 2;
    const ERROR_BOTH_VIDEO_DATA_AND_VIDEO_ID_SUBMITTED = 3;
    const ERROR_NO_VIDEO_ID = 4;
    const ERROR_INVALID_FILE_TYPE = 5;
    const ERROR_VIDEO_DATA_AND_VIDEO_ID = 6;
    const ERROR_INVALID_UPLOAD_OPTION = 7;
    const ERROR_CMS_API_REQUEST_FAILED = 8;
    const ERROR_DYNAMIC_INGEST_API_REQUEST_FAILED = 9;
    const ERROR_S3_INFORMATION_API_REQUEST_FAILED = 10;
    const ERROR_CLIENT_CREDENTIALS_NOT_PROVIDED = 12;
    const ERROR_WRITE_API_TRANSACTION_FAILED = 13;
    const ERROR_CLIENT_SECRET_NOT_PROVIDED = 14;
    const ERROR_NO_SRCLANG_FOR_TEXT_TRACKS = 15;
    const ERROR_SEARCH_TERMS_NOT_PROVIDED = 16;
    const ERROR_INVALID_JSON_VIDEO_DATA = 17;
    const ERROR_INVALID_JSON_INGEST_DATA = 18;
    const ERROR_INVALID_JSON_FILES_DATA = 19;
    const ERROR_INVALID_JSON_TEXT_TRACKS_DATA = 20;
    const ERROR_INVALID_PATH_FILES_DATA = 21;
    const ERROR_INVALID_PATH_TEXT_TRACKS = 22;
    const ERROR_API_ERROR = 23;
    const ERROR_INVALID_CALL = 24;

    protected $access_token = null;
    protected $account_data = null;
    protected $account_id = null;
    protected $auth_string = null;
    protected $bit32 = false;
    protected $client_id = null;
    protected $client_secret = null;
    protected $cms_data = null;
    protected $di_data = null;
    protected $is_new_video = true;
    protected $is_pull_request = true;
    protected $job_id = null;
    protected $job_status = null;
    protected $options = null;
    protected $parsed_data = null;
    protected $result_parsed = null;
    protected $s3 = null;
    protected $s3Credentials = null;
    protected $text_tracks_data = null;
    protected $token_expires = null;
    protected $url_cms = 'https://cms.api.brightcove.com/v1/accounts/';
    protected $url_di = 'https://ingest.api.brightcove.com/v1/accounts/';
    protected $url_oauth = 'https://oauth.brightcove.com/v3/access_token?grant_type=client_credentials';
    protected $video_id = null;
    protected $responses = null;

    /**
     * The constructor for the BCDIAPI class.
     *
     * @since 0.1.0
     *
     * @param string [$account_id] The Video Cloud account id (required)
     * @param string [$client_id] The read API token for the Brightcove account (required)
     * @param string [$client_secret] The write API token for the Brightcove account (required)
     */
    public function __construct($account_data = null)
    {
        $this->account_data = json_decode($account_data);
        $this->account_id = (isset($this->account_data->account_id))? $this->account_data->account_id : null;
        $this->client_id = (isset($this->account_data->client_id))? $this->account_data->client_id : null;
        $this->client_secret = (isset($this->account_data->client_secret))? $this->account_data->client_secret : null;
        $this->auth_string = $this->client_id.':'.$this->client_secret;
        $this->bit32 = ((string) '99999999999999' == (int) '99999999999999') ? false : true;
        $this->di_data = new stdClass();
        $this->cms_data = new stdClass();
        $this->responses = new stdClass();
        $this->access_token = null;
        $this->video_id = null;
        // Instantiate an Amazon S3 client.
    }


    /**
     * Adds media (videos, images, text tracks) to the account.
     *
     * @since 0.1.0
     *
     * @param object   $ingest_options options for the ingest request
     * @param object   $ingest_options->$video_options Metadata for the video - see [Dyanamic Ingest API reference](http://docs.brightcove.com/en/video-cloud/di-api/reference/versions/v1/index.html#api-Video-Create_Video_Object)
     * @param string   $ingest_options->$video_file video file location (for push-based ingestion; required if assets will be uploaded using the source file upload API)
     * @param string  $ingest_options->$video_id video_id for a replace or retranscode request (optional)
     *
     * @return object status of the ingest
     */
    public function ingest_request($ingest_options)
    {
        if (!isset($this->account_id)) {
            throw new BCDIAPIAccountIdNotProvided($this, self::ERROR_ACCOUNT_ID_NOT_PROVIDED);
        } else if (!isset($this->client_id) || !isset($this->client_secret)) {
            throw new ERROR_CLIENT_CREDENTIALS_NOT_PROVIDED($this, self::ERROR_CLIENT_SECRET_NOT_PROVIDED);
        }
        if (isset($ingest_options->video_options)) {
            $this->cms_data = $ingest_options->video_options;
            $this->is_new_video = true;
        }
        $this->di_data = $ingest_options->ingest_options;
        $cms_decoded = json_decode($this->cms_data);
        $di_decoded = json_decode($this->di_data);
        $this->responses->s3 = array();
        $this->responses->putFiles = array();
        // get video id if any
        if (isset($ingest_options->video_id)) {
            // it's either a replace or retransode
            $this->video_id = $ingest_options->video_id;
            $this->is_new_video = false;
        }

        // is this a push job?
        if (isset($ingest_options->file_paths)) {
            $this->is_pull_request = false;
            $files = json_decode($ingest_options->file_paths);
        }

        // data in place, make api requests
        if ($this->is_new_video) {
            // new additions
            $this->make_request('create_video', $this->cms_data);
            if ($this->is_pull_request) {
                $this->make_request('ingest_video', $this->di_data);
            } else {
                // push request
                // get filenames and S3 paths
                $di_decoded = $this->process_files($files, $di_decoded);
                // do text tracks if any
                if (isset($ingest_options->text_tracks)) {
                    $text_tracks = json_decode($ingest_options->text_tracks);
                    $di_decoded = $this->process_text_tracks($text_tracks, $di_decoded);
                }
                // now update the ingest data
                $this->di_data = json_encode($di_decoded);
                // and do the ingest
                $this->make_request('ingest_video', $this->di_data);
                }
        } else {
            // existing video
            if ($this->is_pull_request) {
                $this->make_request('ingest_video', $this->di_data);
            } else {
                // push request
                // get filenames and S3 paths
                $di_decoded = $this->process_files($files, $di_decoded);
                // now update the ingest data
                $this->di_data = json_encode($di_decoded);
                // and do the ingest
                $this->make_request('ingest_video', $this->di_data);
            }
        }
        return $this->responses;
    }

    /**
     * prepare files, push them to S3, and adjust data for DI request
     *
     * @since 0.1.0
     *
     * @param  object $files decoded data for files to be pushed
     * @param  object $di_decoded decoded DI request data
     *
     * @return object the updated $di_decoded object
     */
    private function process_files($files, $di_decoded) {
        foreach ($files as $name => $value) {
            $file_data = new stdClass();
            $file_name = $name.'_name';
            $file_data->type = $name;
            $tmpArr = (explode('/', $value));
            $tmpName = array_pop($tmpArr);
            $tmp = urlencode($tmpName);
            $file_data->file_name = $tmp;
            $file_data->path = $value;
            // get the S3 urls
            $s3_response = $this->make_request('get_s3urls', $file_data);
            $file_data->api_request_url = $s3_response->api_request_url;
            $file_data->s3 = $s3_response;
            // make the responses available to the user for debugging purposes
            array_push($this->responses->s3, $s3_response);
            switch ($file_data->type) {
                case 'video':
                    $di_decoded->master = new stdClass();
                    $di_decoded->master->url = $file_data->api_request_url;
                    $file_data->s3->ContentType = 'video/mp4';
                    break;
                case 'poster':
                    $di_decoded->poster = new stdClass();
                    $di_decoded->poster->url = $file_data->api_request_url;
                    $file_data->s3->ContentType = 'image/png';
                    break;
                case 'thumbnail':
                    $di_decoded->thumbnail = new stdClass();
                    $di_decoded->thumbnail->url = $file_data->api_request_url;
                    $file_data->s3->ContentType = 'image/png';
                    break;
                default:
                    // should never get here TODO throw an error
                    break;
            }
            // push the file to S3
            $putFiles_response = $this->make_request('put_files', $file_data);
            array_push($this->responses->putFiles, $putFiles_response);
        }
        return $di_decoded;
    }

    /**
     * prepare text tracks, push them to S3, and adjust data for DI request
     *
     * @since 0.1.0
     *
     * @param  object $text_tracks decoded data for text track files to be pushed
     * @param  object $di_decoded decoded DI request data
     *
     * @return object the updated $di_decoded object
     */
    private function process_text_tracks($text_tracks, $di_decoded) {
        $text_tracks_data = array();
        foreach ($text_tracks as $key => $value) {
            $text_track_data = new stdClass();
            if (isset($value->srclang)) {
                $text_track_data->srclang = $value->srclang;
            } else {
                // TODO throw error if srclang is missing
            }
            if (isset($value->kind)) {
                $text_track_data->kind = $value->kind;
            }
            if (isset($value->label)) {
                $text_track_data->label = $value->label;
            }
            if (isset($value->default)) {
                $text_track_data->default = $value->default;
            }
            $push_data = $value;
            $tmpArr = explode('/', $push_data->path);
            $tmpName = urlencode(array_pop($tmpArr));
            $push_data->file_name = $tmpName;
            // get the S3 urls
            $s3_response = $this->make_request('get_s3urls', $push_data);
            $text_track_data->url = $s3_response->api_request_url;
            $push_data->s3 = $s3_response;
            // make the responses available to the user for debugging purposes
            array_push($this->responses->s3, $s3_response);
            // add this track to the text tracks array
            array_push($text_tracks_data, $text_track_data);
            // push the file to S3
            $putFiles_response = $this->make_request('put_files', $push_data);
            array_push($this->responses->putFiles, $putFiles_response);
        }
        // add text tracks data to di data
        $di_decoded->text_tracks = $text_tracks_data;
        return $di_decoded;
    }

    /**
     * Retrieves an access token if there is not a valid one already, and updates the token expiration.
     *
     * @since 0.1.0
     *
     * @return string Access token
     */
    private function get_access_token()
    {
        if (!isset($this->token_expires) || $this->token_expires < time()) {
            $result = $this->make_request('get_token', null);
            $this->access_token = $result->access_token;
            $this->token_expires = time() + $result->expires_in;
        }

        return $this->access_token;
    }

    /**
     * Formats the request for any API requests and retrieves the data.
     *
     * @since 0.1.0
     *
     * @param string [$call] The requested API method
     * @param mixed [$params] A key-value array of API parameters, or a single value that matches the default
     *
     * @return object An object containing all API return data
     */
    private function make_request($call, $request_data = null)
    {
        $options = array();
        if (isset($request_data) && $call !== 'get_s3urls') {
            $options['data'] = $request_data;
        } else {
            $options['data'] = null;
        }

        switch ($call) {
            case 'get_token':
                $options['url'] = $this->url_oauth;
                $options['method'] = 'POST';
                $options['headers'] = array('Content-type: application/x-www-form-urlencoded');
                $options['user_pwd'] = $this->auth_string;
                $access_token_response = $this->send_request($options);
                return $access_token_response;
                break;
            case 'create_video':
                $options['url'] = $this->url_cms.$this->account_id.'/videos';
                $options['method'] = 'POST';
                $this->get_access_token();
                $options['headers'] = array(
                    'Content-type: application/json',
                    'Authorization: Bearer '.$this->access_token,
                );
                $this->responses->cms = $this->send_request($options);
                $this->video_id = $this->responses->cms->id;
                break;
            case 'get_s3urls':
                $options['url'] = $this->url_di.$this->account_id.'/videos/'.$this->video_id.'/upload-urls/'.$request_data->file_name;
                $options['method'] = 'GET';
                $this->get_access_token();
                $options['headers'] = array(
                    'Authorization: Bearer '.$this->access_token,
                );
                $response = $this->send_request($options);
                return $response;
                break;
            case 'put_files':
                $response = $this->putFileToS3($request_data);
                return $response;
                break;
            case 'ingest_video':
                $options['url'] = $this->url_di.$this->account_id.'/videos/'.$this->video_id.'/ingest-requests';
                $options['method'] = 'POST';
                $this->get_access_token();
                $options['headers'] = array(
                    'Content-type: application/json',
                    'Authorization: Bearer '.$this->access_token,
                );
                $this->responses->di = $this->send_request($options);
                $this->job_id = $this->responses->di->id;
                break;
            case 'get_status':
                $options['url'] = $this->url_di.$this->account_id.'/videos/'.$this->video_id.'/ingest_jobs/'.$this->job_id;
                $options['method'] = 'GET';
                $this->get_access_token();
                $options['headers'] = array(
                    'Content-type: application/json',
                    'Authorization: Bearer '.$this->access_token,
                );
                $this->responses->status = $this->send_request($options);
                break;
            default:
                throw new BCDIAPIInvalidMethod($this, self::ERROR_INVALID_CALL);
                break;
        }
    }

    /**
     * Formats a media asset name to be search-engine friendly.
     *
     * @since 0.1.0
     *
     * @param string [$name] The asset name
     *
     * @return string The SEF asset name
     */
    public function sef($name)
    {
        $accent_match = array('Â', 'Ã', 'Ä', 'À', 'Á', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ð', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ');
        $accent_replace = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 'B', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y');

        $name = str_replace($accent_match, $accent_replace, $name);
        $name = preg_replace('/[^a-zA-Z0-9\s]+/', '', $name);
        $name = preg_replace('/\s/', '-', $name);

        return $name;
    }

    /**
     * Retrieves API data from provided URL.
     *
     * @since 0.1.0
     *
     * @param string [$url] The complete API request URL
     * @param string [$method] The HTTP method for the request
     * @param array [$headers] The HTTP headers to send with the request
     * @param string [$data_string] A JSON string containing the request body (if any) to send with the request
     *
     * @return object An object containing all API return data
     */
    protected function send_request($options = null)
    {
        $response = $this->curlRequest($options);

        if ($response && $response != 'null') {
            $response_object = json_decode(preg_replace('/[[:cntrl:]]/u', '', $response));

            if (isset($response_object->error)) {
                if ($this->timeout_retry && $response_object->code == 103 && $this->timeout_current < $this->timeout_attempts) {
                    if ($this->timeout_delay > 0) {
                        if ($this->timeout_delay < 1) {
                            usleep($this->timeout_delay * 1000000);
                        } else {
                            sleep($this->timeout_delay);
                        }
                    }

                    return $this->send_request($url);
                } else {
                    throw new BCDIAPIApiError($this, self::ERROR_API_ERROR, $response_object);
                }
            } else {
                $data = $response_object;

                return $data;
            }
        } else {
            throw new BCDIAPIApiError($this, self::ERROR_API_ERROR);
        }
    }


    /**
     * Makes a cURL request.
     *
     * @since 0.1.0
     *
     * @param mixed [$request] URL to fetch or the data to send via POST
     * @param bool [$get_request] If false, send POST params
     */
    protected function curlRequest($options)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $options['url']);
        if (isset($options['headers'])) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $options['headers']);
        }
        if ($options['method'] === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            if (isset($options['data'])) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $options['data']);
            }
            if (isset($options['user_pwd'])) {
                curl_setopt($curl, CURLOPT_USERPWD, $options['user_pwd']);
            }
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        $curl_error = null;

        if (curl_errno($curl)) {
            $curl_error = curl_error($curl);
        }

        curl_close($curl);

        if ($curl_error !== null) {
            // TODO
        }

        return $this->bit32clean($response);
    }

    /**
     * putFileToS3 puts a file to the S3 bucket for push-based ingest
     *
     * @since 0.1.0
     *
     * @param  [Object] $request_data request data for the request - must in inclue a path and S3 bucket credentials
     *
     * @return [Object] the response from S3
     *
     */
    protected function putFileToS3($request_data) {
        // create an S3 client
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'credentials' => array(
                'key'    => $request_data->s3->access_key_id,
                'secret' => $request_data->s3->secret_access_key,
                'token'	 => $request_data->s3->session_token
            )
        ]);
        $params = array(
            'bucket' => $request_data->s3->bucket,
            'key' => $request_data->s3->object_key
        );
        $uploader = new MultipartUploader($this->s3, $request_data->path, $params);
        try {
            $response = $uploader->upload();
            return $response;
        } catch (MultipartUploadException $e) {
            echo $e->getMessage() . "\n";
        }
    }

    /**
     * Cleans the response for 32-bit machine compliance.
     *
     * @since 0.1.0
     *
     * @param string [$response] The response from a cURL request
     *
     * @return string The cleansed string if using a 32-bit machine
     */
    protected function bit32Clean($response)
    {
        if ($this->bit32) {
            $response = preg_replace('/(?:((?:":\s*)(?:\[\s*)?|(?:\[\s*)|(?:\,\s*))+(\d{10,}))/', '\1"\2"', $response);
        }

        return $response;
    }

    // function below currently not used - saving for future exception handling
    /**
     * Determines if provided type is valid.
     *
     * @since 0.1.0
     *
     * @param string [$type] The type
     */
    protected function validType($type)
    {
        if (!in_array(strtolower($type), $this->valid_types)) {
            throw new BCDIAPIInvalidType($this, self::ERROR_INVALID_TYPE);
        } else {
            return true;
        }
    }


    // function below currently not used - saving for future exception handling
    /**
     * Converts an error code into a textual representation.
     *
     * @since 0.1.0
     *
     * @param int [$error_code] The code number of an error
     *
     * @return string The error text
     */
    public function getErrorAsString($error_code)
    {
        switch ($error_code) {
            case self::ERROR_API_ERROR:
                return 'API error';
                break;
            case self::ERROR_INVALID_JSON_ACCOUNT_DATA:
                return 'No valid JSON for account data was found';
                break;
            case self::ERROR_ACCOUNT_ID_NOT_PROVIDED:
                return 'Account data did not include an account_id';
                break;
            case self::ERROR_BOTH_VIDEO_DATA_AND_VIDEO_ID_SUBMITTED:
                return 'Provideo video_data for new videos or video_id for replace and retranscode request - you may not submit both';
                break;
            case self::ERROR_NO_VIDEO_ID:
                return 'video_id is required for replace and retranscode requests';
                break;
            case self::ERROR_INVALID_FILE_TYPE:
                return 'The video type is not supported';
                break;
            case self::ERROR_INVALID_PROPERTY:
                return 'Requested property not found';
                break;
            case self::ERROR_INVALID_TYPE:
                return 'Type not specified';
                break;
            case self::ERROR_INVALID_UPLOAD_OPTION:
                return 'An invalid media upload parameter has been set';
                break;
            case self::ERROR_READ_API_TRANSACTION_FAILED:
                return 'Read API transaction failed';
                break;
            case self::ERROR_CLIENT_CREDENTIALS_NOT_PROVIDED:
                return 'Client id not provided';
                break;
            case self::ERROR_SEARCH_TERMS_NOT_PROVIDED:
                return 'Search terms not provided';
                break;
            case self::ERROR_WRITE_API_TRANSACTION_FAILED:
                return 'Write API transaction failed';
                break;
            case self::ERROR_CLIENT_SECRET_NOT_PROVIDED:
                return 'Client secret not provided';
                break;
            case self::ERROR_NO_VIDEO_ID:
                return 'If you are not ingesting a new video, a video id is required';
                break;
        }
    }
}

class BCDIAPIException extends Exception
{
    /**
     * The constructor for the BCDIAPIException class.
     *
     * @since 0.1.0
     *
     * @param object [$obj] A pointer to the BCDIAPI class
     * @param int [$error_code] The error code
     * @param string [$raw_error] Any additional error information
     */
    public function __construct(BCDIAPI $obj, $error_code, $raw_error = null)
    {
        $error = $obj->getErrorAsString($error_code);

        if (isset($raw_error)) {
            if (isset($raw_error->error) && isset($raw_error->error->message) && isset($raw_error->error->code)) {
                $raw_error = $raw_error->error;
            }

            $error .= "'\n";
            $error .= (isset($raw_error->message) && isset($raw_error->code)) ? '== '.$raw_error->message.' ('.$raw_error->code.') =='."\n" : '';
            $error .= isset($raw_error->errors[0]) ? '== '.$raw_error->errors[0]->error.' ('.$raw_error->errors[0]->code.') =='."\n" : '';
        }

        parent::__construct($error, $error_code);
    }
}

class BCDIAPIInvalidJsonAccountData extends BCDIAPIException{}
class BCDIAPIAccountIdNotProvided extends BCDIAPIException{}
class BCDIAPIBothVideoDataAndVideoIdSubmitted extends BCDIAPIException{}
class BCDIAPINoVideoId extends BCDIAPIException{}
class BCDIAPIInvalidFileType extends BCDIAPIException{}
class BCDIAPIVideoDataAndVideoId extends BCDIAPIException{}
class BCDIAPIInvalidUploadOption extends BCDIAPIException{}
class BCDIAPICmsApiRequestFailed extends BCDIAPIException{}
class BCDIAPIDynamicIngestApiRequestFailed extends BCDIAPIException{}
class BCDIAPIS3InformationApiRequestFailed extends BCDIAPIException{}
class BCDIAPIClientCredentialsNotProvided extends BCDIAPIException{}
class BCDIAPIWriteApiTransactionFailed extends BCDIAPIException{}
class BCDIAPIClientSecretNotProvided extends BCDIAPIException{}
class BCDIAPINoSrclangForTextTracks extends BCDIAPIException{}
class BCDIAPISearchTermsNotProvided extends BCDIAPIException{}
class BCDIAPIInvalidJsonVideoData extends BCDIAPIException{}
class BCDIAPIInvalidJsonIngestData extends BCDIAPIException{}
class BCDIAPIInvalidJsonFilesData extends BCDIAPIException{}
class BCDIAPIInvalidJsonTextTracksData extends BCDIAPIException{}
class BCDIAPIInvalidPathFilesData extends BCDIAPIException{}
class BCDIAPIInvalidPathTextTracks extends BCDIAPIException{}
class BCDIAPIApiError extends BCDIAPIException{}
class BCDIInvalidCall extends BCDIAPIException{}
?>
