<?php

/**
 * Forum interface for websites
 * @author Davide Cester, Padova, Italy, davide@cester.net
 * @copyright LGPL
 * @version 0.9
 * supported forums: phpbb3.0, phpbb3.1
 */

class PhpbbAgent {
	// variables
	private $conn;
	private $bver;
	// constructor
	function __construct($hostname,$username,$password,$database,$phpbb3ver=0) {
		$this->conn = new mysqli($hostname,$username,$password,$database);
		if ($this->conn->connect_error) {
			die("Connect Error (" . $this->conn->connect_errno . ") ".$this->conn->connect_error);
		}
		$this->bver=$phpbb3ver;
	}
	// destructor
	function __destruct() {
		if (is_resource($this->conn)) {
			$this->conn->close();
		}
	}
	// posting
	function postForumMessage($username,$fid,$subj,$text,$lock=false) {
		$return_ids['forum'] = $fid;
		$return_ids['topic'] = 0;
		$return_ids['fpost'] = 0;
		// recupero dati utente
		$res = $this->conn->query("SELECT user_id, username, user_colour FROM phpbb_users WHERE username='".addslashes($username)."';");
		$row_user = $res->fetch_assoc();
		// preparazione testo del messaggio
		$bbcode_uid = substr(md5(mt_rand()), 0, 8);
		$post_text = preg_replace("/\]/",":".$bbcode_uid."]",$text);
		$post_text = addslashes($post_text);
		$post_subj = addslashes($subj);
		$post_time = time();
		// creazione del topic in forum
		$query  = "INSERT INTO phpbb_topics (forum_id,topic_title,topic_poster,topic_time,";
		$query .= "topic_first_poster_name,topic_first_poster_colour,topic_last_post_subject,";
		$query .= "topic_last_poster_name,topic_last_poster_colour,topic_last_post_time)";
		$query .= " VALUES ('".$fid."','".$post_subj."','".$row_user['user_id']."','".$post_time."',";
		$query .= "'".$row_user['username']."','".$row_user['user_colour']."','".$post_subj."',";
		$query .= "'".$row_user['username']."','".$row_user['user_colour']."','".$post_time."')";
//	echo $query."<br>";
		$this->conn->query($query);
//	echo $this->conn->error."<br>";
		$tid = $this->conn->insert_id;
		// creazione del primo post dentro al topic
		$query  = "INSERT INTO phpbb_posts (topic_id,forum_id,poster_id,post_time,";
		$query .= "post_subject,post_text,bbcode_uid,bbcode_bitfield)";
		$query .= " VALUES ('".$tid."','".$fid."','".$row_user['user_id']."','".$post_time."',";
		$query .= "'".$post_subj."','".$post_text."','".$bbcode_uid."','Vw==');";
//	echo $query."<br>";
		$this->conn->query($query);
//	echo $this->conn->error."<br>";
		$pid = $this->conn->insert_id;
		// PHPBB 3.1 ONLY
		if ($this->bver==1) { $this->conn->query("UPDATE phpbb_posts SET post_visibility=1 WHERE post_id=".$pid.";"); }
		// end 3.1 only
		// aggiornamenti campi del topic
		$query  = "UPDATE phpbb_topics SET";
		// PHPBB 3.1 ONLY
		if ($this->bver==1) { $query .= " topic_visibility=1, topic_posts_approved=1,"; }
		// end 3.1 only
		$query .= " topic_title='".$post_subj."',";
		$query .= " topic_poster='".$row_user['user_id']."',";
		$query .= " topic_first_poster_name='".$row_user['username']."',";
		$query .= " topic_first_poster_colour='".$row_user['user_colour']."',";
		$query .= " topic_last_poster_id='".$row_user['user_id']."',";
		$query .= " topic_last_poster_name='".$row_user['username']."',";
		$query .= " topic_last_poster_colour='".$row_user['user_colour']."',";
		$query .= " topic_last_post_subject='".$post_subj."'";
		$query .= " WHERE topic_id = ".$tid.";";
//	echo $query."<br>";
		$this->conn->query($query);
//	echo $this->conn->error."<br>";
		if ($lock) { $this->conn->query("UPDATE phpbb_topics SET topic_status = 1 WHERE topic_id = ".$tid.";"); }
		// aggiornamenti campi del forum
		$query  = "UPDATE phpbb_forums SET";
		if ($this->bver==0) {	// PHPBB 3.0
			$query .= " forum_posts = forum_posts + 1,";
			$query .= " forum_topics = forum_topics + 1,";
			$query .= " forum_topics_real = forum_topics_real + 1,";
		} else {	// PHPBB 3.1
			$query .= " forum_posts_approved = forum_posts_approved + 1,";
			$query .= " forum_topics_approved = forum_topics_approved + 1,";
		}
		$query .= " forum_last_post_time = '".$post_time."',";
		$query .= " forum_last_post_id = '".$pid."',";
		$query .= " forum_last_poster_id='".$row_user['user_id']."',";
		$query .= " forum_last_post_subject='".$post_subj."',";
		$query .= " forum_last_poster_name='".$row_user['username']."',";
		$query .= " forum_last_poster_colour='".$row_user['user_colour']."'";
		$query .= " WHERE forum_id=".$fid.";";
//	echo $query."<br>";
		$this->conn->query($query);
//	echo $this->conn->error."<br>";
		// aggiornamento numero post utente
		$query	= "UPDATE phpbb_users SET user_posts = user_posts + 1 WHERE user_id = ".$row_user['user_id'].";";
//	echo $query."<br>";
		$this->conn->query($query);
//	echo $this->conn->error."<br>";
		// return values
		$return_ids['topic'] = $tid;
		$return_ids['fpost'] = $pid;
		return $return_ids;
	}
	// editing
	function editForumMessage($username,$fid,$tid,$pid,$subj,$text,$lock=false) {
		// recupero dati utente
		$res = $this->conn->query("SELECT user_id, username, user_colour FROM phpbb_users WHERE username='".addslashes($username)."';");
		$row_user = $res->fetch_assoc();
		// eliminazione allegati
		//$res = $this->conn->query("SELECT physical_filename from phpbb_attachments where post_msg_id=".$pid.";");
		//while ($row = $res->fetch_assoc()) { unset("forum/images/".$row['physical_filename']); }
		//$res = $this->conn->query("DELETE FROM phpbb_attachments where post_msg_id=".$pid.";");
		// preparazione messaggio
		$bbcode_uid = substr(md5(mt_rand()), 0, 8);
		$post_text = preg_replace("/\]/",":".$bbcode_uid."]",$text);
		$post_text = addslashes($post_text);
		$post_subj = addslashes($subj);
		// aggiornamento campi del post
		$query  = "UPDATE phpbb_posts SET";
		$query .= " poster_id='".$row_user['user_id']."',";
		$query .= " post_subject='".$post_subj."',";
		$query .= " post_text='".$post_text."',";
		$query .= " bbcode_uid='".$bbcode_uid."',";
		$query .= " bbcode_bitfield='Vw=='";
		$query .= " WHERE post_id=".$pid.";";
		$this->conn->query($query);
		// aggiornamenti campi del topic
		$query  = "UPDATE phpbb_topics SET";
		$query .= " topic_title='".$post_subj."',";
		$query .= " topic_poster='".$row_user['user_id']."',";
		$query .= " topic_first_poster_name='".$row_user['username']."',";
		$query .= " topic_first_poster_colour='".$row_user['user_colour']."',";
		$query .= " topic_last_poster_id='".$row_user['user_id']."',";
		$query .= " topic_last_poster_name='".$row_user['username']."',";
		$query .= " topic_last_poster_colour='".$row_user['user_colour']."',";
		$query .= " topic_last_post_subject='".$post_subj."'";
		$query .= " WHERE topic_id = ".$tid.";";
		$this->conn->query($query);
		if ($lock) { $this->conn->query("UPDATE phpbb_topics SET topic_status = 1 WHERE topic_id = ".$tid.";"); }
		// aggiornamenti campi del forum
		$query  = "UPDATE phpbb_forums SET";
		$query .= " forum_last_poster_id='".$row_user['user_id']."',";
		$query .= " forum_last_post_subject='".$post_subj."',";
		$query .= " forum_last_poster_name='".$row_user['username']."',";
		$query .= " forum_last_poster_colour='".$row_user['user_colour']."'";
		$query .= " WHERE forum_last_post_id=".$pid.";";
		$this->conn->query($query);
		return $pid;
	}
}

?>
