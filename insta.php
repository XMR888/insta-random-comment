<?php
	
	/********************************************************
	*	@author yuric.info
	*	@writed_for http://tortalina.ru
	*	@date 12 dec 2018
	*	This simple code is written on the knee with love.
	*	It may work, may not work, as lucky.
	*	Please do not use for commercial purposes.	
        * ! Not load more than 600 comments per request.
        * ! Required comments cache first
	********************************************************/
	
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/insta-php-error.log");
error_reporting(E_ALL);
ini_set('display_errors', true);
define('CACHE_DIR','/tmp/instacache');
if (!is_dir(CACHE_DIR)) {
	mkdir(CACHE_DIR);
}
$insta_url = !empty($_REQUEST['iu']) ? parse_url(urldecode($_REQUEST['iu'])) : false;

if (empty($insta_url['host']) || ($insta_url['host'] != 'www.instagram.com')) {
	# Если нет урла
	$result['code'] = '999';
	$result['message'] = 'No insta page URL';
	die(json_encode($result));
}
$get_page_url = $insta_url['scheme'].'://' . $insta_url['host'] . $insta_url['path'];
$cache_file = md5($get_page_url).'.cache';
if (file_exists('/tmp/'.$cache_file) && file_exists('/tmp/'.$cache_file.'.base')) {
	$comms = json_decode(file_get_contents('/tmp/'.$cache_file),true);
	$range = count($comms);
	$base = json_decode(file_get_contents('/tmp/'.$cache_file.'.base'));
	$top_post_image = $base->url;
	$post_id = $base->id;
	$post_code = $base->code;
	$post_author = $base->author;
	$post_likes = $base->likes;
} else {
  # github @IntstagramScrapper
	require __DIR__ . '/vendor/autoload.php';
	try {
    # Login and password for instagram
		$instagram = \InstagramScraper\Instagram::withCredentials('INSTALOGIN', 'INSTAPASS', CACHE_DIR);
		$instagram->login();
		$media = $instagram->getMediaByUrl($get_page_url);
	} catch (Exception $e) {
		error_log($e->getMessage());
		$result['code'] = '996';
		$result['message'] = 'Insta not loaded data (0).';
		die(json_encode($result));
	}
	
	if (empty($media->getId())) {
			$result['code'] = '999';
			$result['message'] = 'No insta page URL';
			die(json_encode($result));
	}
	$top_post_image = $media->getImageHighResolutionUrl();
	$post_id = $media->getId();
	$post_code = $media->getShortCode();
	$range = $media->getCommentsCount();
	$account = $media->getOwner();
	$post_author = $account->getUsername();
	$post_likes = $media->getLikesCount();
	if (empty($top_post_image)) {
		# Если нет главной фотки умираем
		$result['code'] = '998';
		$result['message'] = 'Not found photo';
		die(json_encode($result));
	}
	try {
		$comments = $instagram->getMediaCommentsByCode($post_code,$range);
	} catch (Exception $e) {
		error_log($e->getMessage());
		$result['code'] = '996';
		$result['message'] = 'Insta not loaded data.';
		die(json_encode($result));
	}
	if (empty($comments)) {
		# Если нет комментов умираем
		$result['code'] = '997';
		$result['message'] = 'Not found any comments';
		die(json_encode($result));
	}
	$loaded = 0;
	foreach($comments as $comment) {
		$com_date = $comment->getCreatedAt();
		$comms[$com_date]['text'] = $comment->getText();
		$account = $comment->getOwner();
		$comms[$com_date]['username'] = $account->getUsername();
		$comms[$com_date]['ava'] = $account->getProfilePicUrl();
		$comms[$com_date]['date'] = $com_date;
		++$loaded;
	}
	ksort($comms);
	# Сохраняем в кеш
	file_put_contents('/tmp/'.$cache_file, json_encode($comms));
	$base_data = array(
		'url' => $top_post_image,
		'id' => $post_id,
		'code' => $post_code,
		'author' => $post_author,
		'likes' => $post_likes
	);
	file_put_contents('/tmp/'.$cache_file.'.base', json_encode($base_data));
}
# Данные на страницу
$result['code'] = '200';
$result['topimage'] = $top_post_image;
$result['range'] = $range;
$result['urlhref'] = $get_page_url;
$result['postauthor'] = $post_author;
$result['postlikes'] = $post_likes;
srand();
$randomresult = rand(0,$range);
$result['maxrange'] = $range+1;
$result['winresult'] = $randomresult;
$wincomment = array_get_by_index($randomresult,$comms);
$result['winnername'] = $wincomment['username'];
$result['winnerprofile'] = 'https://www.instagram.com/'.$result['winnername'];
$result['winnerphoto'] = $wincomment['ava'];
$result['winnercomment'] = $wincomment['text'];
die(json_encode($result));

function array_get_by_index($index, $array) {
    $i=0;
    foreach ($array as $value) {
        if($i==$index) {
            return $value;
        }
        $i++;
    }
    // may be $index exceedes size of $array. In this case NULL is returned.
    return NULL;
}
?>
