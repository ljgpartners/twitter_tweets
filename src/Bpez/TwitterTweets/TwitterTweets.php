<?php

use Made\Services\freebird\Client;

// To get access tokens go here:
// https://dev.twitter.com/apps/new 

  // Define the main class
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

    // Define the constructor
    public function __construct($username, $yourKey , $yourSecretKey, $numTweets = null)
    {
      // We make a default username in case the username is not set
      $this->user = $username;

      $this->count = ($numTweets != null)? $numTweets : $this->count;

      // Setup freebird Client with Twitter application keys
      $this->client = new Client();

      $this->client->init_bearer_token($yourKey, $yourSecretKey);
    }

    // Get the data from the URL
    private function fetch_url($username)
    {

      $response = $this->client->api_request('statuses/user_timeline.json', array('screen_name' => $username, 'count' => $this->count, 'trim_user' => $this->trim_user));
      $tweets = json_decode($response);

      // Check if we reach the requests limit
      if(is_object($tweets)){
        return false;
      }else{
        return $tweets;
      }
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
        echo "bryan2";
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