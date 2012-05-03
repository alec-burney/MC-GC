<?php
	
	require_once 'inc/config.php.private'; //thar be settings
	require_once 'inc/MCAPI.class.php';
	require_once 'Zend/Loader.php';
	
	Zend_Loader::loadClass('Zend_Gdata');
	Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
	Zend_Loader::loadClass('Zend_Http_Client');
	Zend_Loader::loadClass('Zend_Gdata_Query');
	Zend_Loader::loadClass('Zend_Gdata_Feed');
    

	$MC['api'] = new MCAPI($MC['apikey']);

	$shortopts = "vvym:x:s:u:c:";
	$longopts = array(
		"sync",
		"members:",
		"exmembers:",
		"subscribe:",
		"unsubscribe:",
		"clean:"
	);

	$options = getopt($shortopts, $longopts);
	
	if(isset($options['v']))
	{
    $v = count($options['v']);
		if($v > 1)
		{
			echo "options: ";
			var_dump($options);
		}
	}

	if(isset($options['y']) OR isset($options['sync']))
	{
		sync_all_members();
	}

	if(isset($options['subscribe']))
	{
    $to_subscribe = explode(',',$options[subscribe]);
		subscribe_new($to_subscribe);	
		return 1;	
	}

	if(isset($options['s']))
	{
    $to_subscribe = explode(',',$options[s]);
		subscribe_new($to_subscribe);	
		return 1;	
	}
	if(isset($options['u']) OR isset($options['unsubscribe']))
	{
		
		return 1;
	}

	if(isset($options['c']))
	{

		return 1;
	}

	if(isset($options['members']))
	{
		get_subscribed_members($options['members']);
	}


	if(isset($options['m']))
	{
		get_subscribed_members($options['m']);
	}

	if(isset($options['exmembers']))
	{
		get_unsubscribed_members($options['exmembers']);
	}


	if(isset($options['x']))
	{
		get_unsubscribed_members($options['x']);
	}

function get_subscribed_members($service) {
	global $v;
	if($service == "MC")
	{
	global $MC;
//		$retval = $api->listMembers($listId, 'subscribed', null, 0, 5000 );
//		var_dump($retval);
		$chunk_size = 4096; //in bytes
		$url = 'http://us5.api.mailchimp.com/export/1.0/list?apikey='.$MC['apikey'].'&id='.$MC['listId'];

		/** a more robust client can be built using fsockopen **/
		$handle = @fopen($url,'r');
		if (!$handle) {
		  echo "failed to access url\n";
			} else {
		  $i = 0;
		  $header = array();
		  while (!feof($handle)) {
				$aggregated = NULL;
		    $buffer = fgets($handle, $chunk_size);
		    if (trim($buffer)!=''){
		      $obj = json_decode($buffer);
		      if ($i==0){
			      //store the header row
		       $header = $obj;
		     } else {
			      //echo, write to a file, queue a job, etc.
						if($v>>11) {
							echo $header[0].': '.$obj[0]."\n";  //email
							echo $header[1].': '.$obj[1]."\n";  //frname
							echo $header[2].': '.$obj[2]."\n";  //lname
							echo $header[15].': '.$obj[15]."\n";  //modtime
						}

						$aggregated['fname'] = $obj[1];
						$aggregated['lname'] = $obj[2];
						$aggregated['mtime'] = $obj[15];
						$entries[$obj[0]] = $aggregated;
					}
		      $i++;
		    }
		  }
		  fclose($handle);
		}


  } else if ($service == "GC")
	{
    global $GC;
  
		 try {
      // perform login and set protocol version to 3.0
      $client = Zend_Gdata_ClientLogin::getHttpClient(
        $GC['user'], $GC['pass'], 'cp');
      $gdata = new Zend_Gdata($client);
      $gdata->setMajorProtocolVersion(3);
      
      // perform query and get result feed
      $query = new Zend_Gdata_Query(
        'http://www.google.com/m8/feeds/contacts/default/full');
      $query->maxResults = 1000; //FIXME
      $query->setParam('orderby', 'lastmodified');
			$query->setParam('sortorder', 'descending');
			$query->setParam('group', 'http://www.google.com/m8/feeds/groups/makauwahi@gmail.com/base/'.$GC['subscribed_group']);
      $feed = $gdata->getFeed($query);
      if($v)
			{
        echo "there are " . $feed->totalResults . " on list in GC\n";
			}
			foreach($feed as $entry) {
				if($v>1) {
					static $i=0;
					echo "\n";
					echo 'entry ' . $i . ":\n";
					$i++;
				}

				$aggregated = NULL;
				$xml = simplexml_load_string($entry->getXML());
        foreach($xml->email as $e) {
          //how do we handle multiple emails?

				 //this is actually a SimpleXML Obj, so cast it to string
					$email = (string)$e['address'];
					$mtime = $entry->updated->text;
					if($v>1){
						echo 'found ' . $email . ' from GC' . "\n";
						echo 'last changed at ' . $mtime . "\n";
					}
				}
				
					$fname = (string)$xml->name->givenName . ' ' . (string)$xml->name->additionalName;
					$lname = (string)$xml->name->familyName;
						//how do we handle multiple names?
					if($v>1) {
						echo 'fname: ' . $fname . ' lname: ' . $lname . "\n";
					}

					$aggregated['fname'] = $fname;
					$aggregated['lname'] = $lname;
					$aggregated['mtime'] = $mtime;
					$entries[$email] = $aggregated;
			}
	   } catch (Exception $e) {
      die('ERROR:' . $e->getMessage());  
    }

	}
	if($v) {
		echo "got " . count($entries) . " total from " . $service . "\n";
	}
	return($entries);
}

function sync_all_members() {

	global $MC;
	global $GC;
	global $v;

	$MC['members'] = get_subscribed_members('MC');
	$GC['members'] = get_subscribed_members('GC');
  if($v>1) {
		var_dump($MC['members']);
		var_dump($GC['members']);
	}

	//each person that is not in mailchimp yet, case insensitive
	$new_subscribers = array_diff_ukey($GC['members'], $MC['members'], 'strcasecmp');
	if($v>1) {
		var_dump($new_subscribers);
	}
	if($v) {
		echo "found " . count($new_subscribers) . " new subscribers from gmail\n";
	}
	foreach ($new_subscribers as $email => $data) {
		if($v) {
			echo "adding " . $email . "\n";
		}
	}
}

function get_unsubscribed_members($service) {

	if($service == "MC")
	{
    global $MC;
		$retval = $MC['api']->listMembers($MC['listId'], 'unsubscribed', null, 0, 5000 );
		if($v>1) {
			var_dump($retval);
		}
	}
}

function update_member($service, $member) {


}

//email:firstname:lastname,email2:fistname:lastname,email3:firstname:lastname
  function subscribe_new($to_subscribe) {
		global $MC;
		global $v; 
		static $data = NULL;
 
		foreach ($to_subscribe as $current) {
			
			$temp = explode(":",$current);
			$subscriber[fname] = $temp[1];
			$subscriber[lname] = $temp[2];
			$subscriber[email] = $temp[0];

			if($v) {
        print "email: ". $subscriber[email] . " f: " . $subscriber[fname] . " l: " . $suscriber[lname] . "\n";
      }
			var_dump($data);
			$merge_vars = array('FNAME'=>$data[fname], 'LNAME'=>$data[lname]);
			$retval = $MC['api']->listSubscribe( $MC['listId'], $subscriber[email], $merge_vars, 'html', FALSE );

			if ($MC['api']->errorCode){
				echo "Unable to load listSubscribe()!\n";
				echo "\tCode=".$MC['api']->errorCode."\n";
				echo "\tMsg=".$MC['api']->errorMessage."\n";
			} else {
				echo "Subscribed - " . $current;
			}

		}

	}
?>
