<?php
/*
*
* Facebook comments class | AWD FCBK SEO comments
* (C) 2012 AH WEB DEV
* Hermann.alexandre@ahwebdev.fr
*
*/


class AWD_facebook_comments_base
{
	/**
	* Comments set url
	* string
	*/
	public $comments_url;
	/**
	* Comments FB ID
	* int
	*/
	public $comments_id;
	/**
	* The comments set array
	* array
	*/
	public $comments_array = array();
	/**
	* Comments set url
	* string
	*/
	public $comments_count;
	/**
	* Comments set url
	* string
	*/
	public $limit=0;
	
	/**
	 * Said if the feed is up to date
	*/
	public $comments_status = 0;
	
	/**
	* Wordpress related Post id
	*/
	public $wp_post_id;
	/**
	* The comments set array formated for wordpress template model.
	* array
	*/
	public $wp_comments_model = array();
	
	
	/**
	* Constructor
	*/
	public function __construct($AWD_facebook)
	{
		global $AWD_facebook;
		$this->comments_url = home_url();
		$this->AWD_facebook = $AWD_facebook;
	}
	/**
	* FB API
	*/
	public function set_AWD_facebook()
	{
		global $AWD_facebook;
		$this->AWD_facebook = $AWD_facebook;
	}
	/**
	* Get comments Id by url
	*/
	public function get_comments_id_by_url()
	{
		if($this->comments_url == '') 
			return false;
		$fql = "SELECT comments_fbid,commentsbox_count,comment_count FROM link_stat WHERE url='http://www.ahwebdev.fr/plugins/facebook-awd.html'";
		try {
			$fql_url_object = $this->AWD_facebook->fcbk->api(array('method'=>'fql.query','query'=>$fql));
			$this->comments_count = 0;
			$this->comments_id = 0;
			if(isset($fql_url_object[0]['comments_fbid'])){
				$this->comments_id = $fql_url_object[0]['comments_fbid'];
				$this->comments_count = $fql_url_object[0]['commentsbox_count'];
			}
			$this->update_cache();
			return true;
		}catch (FacebookApiException $e) {
			return $e->getMessage();
		}
		return false;
	}
	/**
	* Get comments by Url.
	*/
	public function get_comments_by_url()
	{
		//Make a query to test if something exist.
		$response = $this->get_comments_id_by_url($this->comments_url);
		if($response == true){
			//if comments exists get them
			if($this->comments_id > 0){
				try {
					$comments_from_url = $this->AWD_facebook->fcbk->api('/'.$this->comments_id.'/comments?limit='.$this->limit);
					$this->comments_array = $comments_from_url['data'];
					$this->update_cache();
					return true;
				}catch (FacebookApiException $e) {
					return $e->getMessage();
				}
			}
		}
		return false;
	}
	/**
	* return the comment count
	* Will be empty until get_comments_id_by_url() is called.
	*/
	public function get_comments_count()
	{
		return $this->comments_count;
	}
	
	/**
	* update data
	*/
	public function update_cache()
	{
		//If we have some infos store them
		if($this->comments_id > 0){
			update_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_infos', 
				array(
					'comments_id' => $this->comments_id,
					'comments_count' => $this->comments_count
				)
			);
		}
		//If we got comments store them
		if($this->comments_array > 0){
			update_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_array', $this->comments_array);
		}
		
		update_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_status', 1);
	}
	/*
	* clear data
	*/
	public function clear_cache()
	{
		$this->comments_array = array();
		delete_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_status');
		delete_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_array');
		delete_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_infos');
	}
	/**
	* Delete a comment
	*/
	public function delete_comment($comment_id)
	{
			try {
				if($this->AWD_facebook->fcbk->api('/'.$comment_id,'delete'))
					return true;
			}catch (FacebookApiException $e) {
				return $e->getMessage();
			}
	}
	/**
	* Post a comment
	*/
	public function post_comment($comment_to_post)
	{
    	if($comment_to_post){
			if($comment_to_post != ''){
				try {
					$comment_posted = $this->AWD_facebook->fcbk->api('/'.$this->comments_id.'/comments','post',array('message'=>$comment_to_post));
					if($comment_posted['id'])
						return $comment_posted['id'];		
				}catch (FacebookApiException $e) {
					return false;
				}		
			}
		}
		return false;
	}

	public function get_comments_from_cache()
	{
		$this->comments_infos = get_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_infos', true);					
		$this->comments_count = $this->comments_infos['comments_count'] > 0 ? $this->comments_infos['comments_count'] : 0;
		$this->comments_id = $this->comments_infos['comments_id'] > 0 ? $this->comments_infos['comments_id'] : 0;				
		$this->comments_array = get_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_array', true);					
		$this->comments_array = count($this->comments_array) > 0 ? $this->comments_array : array();
		$this->comments_status = get_post_meta($this->wp_post_id, '_'.$this->AWD_facebook->plugin_option_pref.'cache_fb_comments_status', true);					
		$this->comments_status =  $this->comments_status > 0 ? $this->comments_status : 0;
	}
	
	
	/**
	* Get the comments from api
	* First try to get from post meta cache, then if no cache(DB),
	* make a call to get new comments and save them.
	*/
	public function wp_get_comments(){
		if($this->comments_url != ''){
			if($this->wp_post_id != ''){	
				//know if we want cache comments or not
				if($this->AWD_facebook->options['comments_cache'] != "0" && $_REQUEST['action'] != 'clear_fb_cache'){
					$this->get_comments_from_cache();	
					if($this->comments_status != 1){
						$reponse = $this->get_comments_by_url();
						$this->update_cache();
					}
				}else{
					$reponse = $this->get_comments_by_url();
				}
			}else{
				$reponse = $this->get_comments_by_url();
			}
			if($reponse !== true)
				return $response;
				
			return $this->comments_array;
        }
        return false;
	}
	/**
	 * Return the data fb comment formated for WP comment_array
	 */
	public function wp_comments_data_model($comment,$comment_parent_id=0){
		$new_fb_comment = "";
		//get user from fb_uid
		$wp_user = $this->AWD_facebook->get_user_from_fbuid($comment['from']['id']);
		$new_fb_comment->comment_ID = str_replace("_",'',$comment['id']);
		$new_fb_comment->comment_post_ID = $this->wp_post_id;
		$new_fb_comment->comment_author = $wp_user->display_name == '' ? $comment['from']['name'] : $wp_user->display_name;
		$new_fb_comment->comment_author_email = $wp_user->user_email;
		$new_fb_comment->comment_approved = 1;
		$new_fb_comment->comment_author_url = 'http://www.facebook.com/'.$comment['from']['id'];
		$new_fb_comment->comment_content = $comment['message'];
		$new_fb_comment->comment_date = $new_fb_comment->comment_date_gmt = date("Y-m-d H:i:s",strtotime($comment['created_time']));
		$new_fb_comment->user_id =  $wp_user->ID == '' ? $comment['from']['id'] : $wp_user->ID;
		$new_fb_comment->comment_parent = $comment_parent_id;
		//if subcomment ad it to other
		if($comment['comments']['data'])
			foreach($comment['comments']['data'] as $subcomment){
				$subwp_comment = $this->wp_comments_data_model($subcomment,$new_fb_comment->comment_ID);
				$response_comment[] = $subwp_comment['wp_comment'];
			}
		
		return array("wp_comment"=>$new_fb_comment,'response_comments'=>$response_comment);
	}

}
?>