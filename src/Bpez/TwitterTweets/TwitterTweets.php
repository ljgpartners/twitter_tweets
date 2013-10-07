<?php 

namespace Bpez\TwitterTweets;

use Guzzle\Http\Client;
use Guzzle\Plugin\Oauth\OauthPlugin;
use DateTime;

// To get access tokens go here:
// https://dev.twitter.com/apps/new 

/*
Once installed you can easily access all of the Twitter API endpoints supported by 
[Application Only Authentication](https://dev.twitter.com/docs/auth/application-only-auth). 
You can view those enpoints [here](https://dev.twitter.com/docs/rate-limiting/1.1/limits). 
*/

// sudo chmod 666 twitter-last-cache.txt
// sudo chmod 666 twitter-cache.json
// Fix permissions of files not necessarly 666

class TwitterTweets {
  
    // The max number of tweets
    private $count = 1;
    // Trim the user informations from the data
    private $trim_user = true;
    // Time between cache (Unit is second)
    private $time = 3600; // 60 min
    // Twitter username
    private $user;

    private $client;

    
    public function __construct($username, $auth, $numTweets = null)
    {
      // We make a default username in case the username is not set
      $this->user = $username;

      $this->count = ($numTweets != null)? $numTweets : $this->count;

      // Create a client to work with the Twitter API
      $this->client = new Client('https://api.twitter.com/{version}', array(
          'version' => '1.1'
      ));

      // Sign all requests with the OauthPlugin
      $this->client->addSubscriber(new OauthPlugin(array(
          'consumer_key'  => $auth['consumer_key'],
          'consumer_secret' => $auth['consumer_secret'],
          'token'       => $auth['token'],
          'token_secret'  => $auth['token_secret']
      )));

    }

    // Get the data from the URL
    private function fetch_url($username)
    {
      try {
        $response = $this->client->get('statuses/user_timeline.json?screen_name='.$username.'&count='.$this->count.'&trim_user='.$this->trim_user)->send()->getBody();
      } catch (\Guzzle\Http\Exception\ClientErrorResponseException $e) {
        //$response = $e->getResponse();
        return false; // Fail safe return false to get cached tweet
      }
      
      $tweets = json_decode($response);

      // Check if we reach the requests limit
      if(is_object($tweets)){
        return false;
      }else{
        return self::filter_links($tweets);
      }
    }

    private static function filter_links($tweets)
    {
      // The Regular Expression filter
      $reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

      foreach($tweets as $t) {      
        // The Text you want to filter for urls
        $text = $t->text;

        // Check if there is a url in the text
        if(preg_match($reg_exUrl, $text, $url)) {
          // make the urls hyper links
          $t->text = preg_replace($reg_exUrl, "<a target='_BLANK' href='{$url[0]}'>{$url[0]}</a> ", $text);
        } else {
          // if no urls in the text just return the text
          $t->text = $text;
        }
      } 

      return $tweets;
    }

    // Save Cache to files
    private function save_cache($data)
    {
      // Save Json data
      $handle = fopen(__DIR__."/twitter-cache.json", 'w');
      fwrite($handle, json_encode($data));
      fclose($handle);
      // Save Last date
      $handle = fopen(__DIR__."/twitter-last-cache.txt", 'w');
      fwrite($handle, date("c"));
      fclose($handle); 
    }

    // Get second from last cache to now
    private function second()
    {
      if (!file_exists(__DIR__."/twitter-last-cache.txt")) {
        // Save Last date
        $handle = fopen(__DIR__."/twitter-last-cache.txt", 'w');
        fwrite($handle, "2000-01-01T12:12:12+00:00");
        fclose($handle); 
      }
      $prevDate = file_get_contents(__DIR__."/twitter-last-cache.txt");
      $dateOne = new DateTime($prevDate);
      $dateTwo = new DateTime(date("c"));
      $diff = $dateTwo->format("U") - $dateOne->format("U");

      return $diff;
    }

    // Get data from cache file
    private function get_data()
    {
      $tweets = json_decode(file_get_contents(__DIR__."/twitter-cache.json"));
      return $tweets;
    }

    // Get the tweets
    public function getTweets()
    {
      // Check if we update the cache or no
      if($this->second() < $this->time){ 
        // Because it's less we read from the cache
        $tweets = $this->get_data();
      }else{
        // We can update the cache 
        $tweets = $this->fetch_url($this->user); 
        if($tweets == false){
          // We check here if the the fetch return false, because this case mean that 
          // we are reach the request limit, so we read from the cache
          $tweets = $this->get_data();
        }else{
          // Here we get the newest data and save the to the cache
          $this->save_cache($tweets);
        }
      }
      return $tweets;
    }
}
?>