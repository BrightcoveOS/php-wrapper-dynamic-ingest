<?php
require '../dist/bc-diapi.php';

$account_info = new stdClass();
$request_type = null;
$video_id = null;
// process input data
if (isset($_POST)) {
    if (isset($_POST['account_id'])) {
        $account_info->account_id = $_POST['account_id'];
    } else {
        echo 'Account id is required!';
    }
    if (isset($_POST['client_id'])) {
        $account_info->client_id = $_POST['client_id'];
    } else {
        echo 'Client id is required!';
    }
    if (isset($_POST['client_secret'])) {
        $account_info->client_secret = $_POST['client_secret'];
    } else {
        echo 'Client secret is required!';
    }
    if (isset($_POST['typeSelect'])) {
        $request_type = $_POST['typeSelect'];
    } else {
        echo 'Request type is required!';
    }
    if (isset($_POST['video_id'])) {
        $video_id = $_POST['video_id'];
    } else {
        echo 'Request type is required!';
    }
} else {
    echo 'Run this app from <a href="index.html">index.html</a>';
}
// sample data

// to ingest new video (pull-based)
$video_metadata = '{"name":"Great Blue Heron - DI Wrapper test","description": "An original nature video","tags": ["nature","bird"]}';
// pull ingest options
$pull_ingest_data = '{"profile": "videocloud-default-v1","capture-images": true,"text_tracks": [{"url": "http://solutions.brightcove.com/bcls/assets/vtt/sample.vtt","srclang": "en","kind": "captions","label": "EN","default": true}],"master": {"url": "http://solutions.brightcove.com/bcls/assets/videos/Great_Blue_Heron.mp4"},"callbacks": ["http://solutions.brightcove.com/bcls/di-api/di-callbacks.php"]}';

// push ingest data
$push_ingest_data = '{"profile": "videocloud-default-v1","capture-images": true,"callbacks": ["http://solutions.brightcove.com/bcls/di-api/di-callbacks.php"]}';

// for retranscode test
$retranscode_data = '{"profile": "videocloud-default-v1","capture-images": false,"master": { "use_archived_master": true },"callbacks": ["http://solutions.brightcove.com/bcls/di-api/di-callbacks.php"]}';

// for replace video test
$account_data = json_encode($account_info);

// for push-based ingest
$file_paths = '{"video": "assets/Great-Blue-Heron.mp4"}';
$file_paths_full = '{"video": "assets/Great-Blue-Heron.mp4","poster": "assets/Great-Blue-Heron.png","thumbnail": "assets/great-blue-heron-thumbnail.png"}';
$text_tracks = '[{"path": "assets/sample.vtt", "srclang": "en","kind": "captions","label": "EN","default": true}]';


// data sets
$data_sets = new stdClass();
// pull request options
$data_sets->pull_options = new stdClass();
$data_sets->pull_options->video_options = $video_metadata;
$data_sets->pull_options->ingest_options = $pull_ingest_data;

// push request options
$data_sets->push_options = new stdClass();
$data_sets->push_options->file_paths = $file_paths;
$data_sets->push_options->video_options = $video_metadata;
$data_sets->push_options->ingest_options = $push_ingest_data;
$data_sets->push_options->text_tracks = $text_tracks;

// pull replace request options
$data_sets->pull_replace_options = new stdClass();
$data_sets->pull_replace_options->video_id = $video_id;
$data_sets->pull_replace_options->video_options = $video_metadata;
$data_sets->pull_replace_options->ingest_options = $pull_ingest_data;

// push replace request options
$data_sets->push_replace_options = new stdClass();
$data_sets->push_replace_options->video_id = $video_id;
$data_sets->push_replace_options->ingest_options = $pull_ingest_data;
$data_sets->push_replace_options->file_paths = $file_paths;

// retranscode request options
$data_sets->retranscode_options = new stdClass();
$data_sets->retranscode_options->video_id = $video_id;
$data_sets->retranscode_options->ingest_options = $retranscode_data;

// instantiate the wrapper
$bcdi = new BCDIAPI($account_data);
echo '<p>Wrapper instantiated</p>';
// make a request - change data param to test other operations
$request_data = $data_sets->$request_type;
echo '<p>Request submitted</p>';
echo '<p>Processing...</p>';
// Create a try/catch
try {
    // make request
    $responses = $bcdi->ingest_request($request_data);
} catch(Exception $error) {
    // Handle our error
    echo $error;
    die();
}
echo '<p>Processing complete</p>';
echo '<h3 style="font-family:sans-serif;">CMS Response (will be NULL for replace/retranscode requests)</h3>';
echo '<pre>'.json_encode($responses->cms, JSON_PRETTY_PRINT).'</pre>';
// echo '<h3 style="font-family:sans-serif;">S3 Responses (will be empty for pull-based ingest)</h3>';
// echo '<pre>'.json_encode($responses->s3, JSON_PRETTY_PRINT).'</pre>';
// echo '<h3 style="font-family:sans-serif;">PUT File Responses (will be empty for pull-based ingest)</h3>';
// echo '<pre>'.json_encode($responses->putFiles, JSON_PRETTY_PRINT).'</pre>';
echo '<h3 style="font-family:sans-serif;">DI Response</h3>';
echo '<pre>'.json_encode($responses->di, JSON_PRETTY_PRINT).'</pre>';
?>
