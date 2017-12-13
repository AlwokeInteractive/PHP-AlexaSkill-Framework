<?php

  class Intent
	{
		private $data;
		function __construct($data)
		{
			$this->data=$data;
		}
		
		public function GetName()
		{
			return str_replace("AMAZON.","",$this->data->name);
		}
		
		public function GetSlotValue($index)
		{
			if (isset($this->data->slots->{$index}->value))
			{
				return $this->data->slots->{$index}->value;
			}
			else
			{
				return null;
			}
		}
	}
	
	abstract class IntentView
	{
		/**
		 * Holds the Response
		 * @var Response
		 */
		private $response;
		
		/**
		 * Holds the given Intent
		 * @var Intent
		 */
		private $intent;
		
		/**
		 * Passes Response to IntentView
		 * @param Response $response
		 */
		function __construct($response,$intent)
		{
			$this->response=$response;
			$this->intent=$intent;
		}
		
		/**
		 * Returns the Reponse, so it can be written
		 * @return Response
		 */
		public function GetResponse()
		{
			return $this->response;
		}
		
		/**
		 * Returns the Intent, so it can be written
		 * @return Intent
		 */
		public function GetIntent()
		{
			return $this->intent;
		}
		
		/**
		 * Pass Intent to Function
		 * @param Intent $intent
		 */
		public function Route($intentType)
		{
			if (method_exists($this,$intentType))
			{
				$this->{$intentType}();
			}
			else
			{
				$this->{"Error"}();
			}
		}
		
		public abstract function Launch();
		public abstract function Error();
	}
	
	class Request
	{
		/**
		 * Mixed Data from Alexa Service
		 * @var array
		 */
		private $data;
		
		/**
		 * Holds the Response
		 * @var Response
		 */
		private $response=null;
		
		function __construct($requestJSON)
		{
			$this->response=new Response();
			$this->data=json_decode($requestJSON);
			$this->Validate($requestJSON);
			if ($this->GetApplicationID()===null)
			{
				die("Invalid Request!");
			}
		}
		
    /**
    * Validate that the Request is signed correctly and is from Amazon
    * @param $data - The plain request data
    */
		public function Validate($data)
		{
			if (!isset($_SERVER['HTTP_SIGNATURECERTCHAINURL']))
			{
				die('No Chain URL found!');
        http_response_code(400);
			}

			$this->IsValidTime();
			$this->IsValidKeyURL($_SERVER['HTTP_SIGNATURECERTCHAINURL']);
			$this->IsValidCert($data);
		}
		
    /**
    * Validates the Request by downloading the Chain Certificate
    * and checking it against the Amazon given Checksum of the Request
    * Outputs the Error and returns a 400 Error Code on failure.
    * @param $data - The plain request data
    */
		public function IsValidCert($data)
		{
			// Determine if we need to download a new Signature Certificate Chain from Amazon
			$md5pem=__DIR__ ."/certs/".md5($_SERVER['HTTP_SIGNATURECERTCHAINURL']).'.pem';
			$echoServiceDomain='echo-api.amazon.com';

			if (!file_exists($md5pem))
			{
				file_put_contents($md5pem,file_get_contents($_SERVER['HTTP_SIGNATURECERTCHAINURL']));
			}
			
			// Validate certificate chain and signature
			$pem=file_get_contents($md5pem);
			$ssl_check=openssl_verify($data,base64_decode($_SERVER['HTTP_SIGNATURE']),$pem,'sha1');
			if ($ssl_check!=1)
			{
				$osslerror=openssl_error_string();
				http_response_code(400);
				die($osslerror);
			}
			
			// Parse certificate for validations below
			$parsedCertificate=openssl_x509_parse($pem);
			if (!$parsedCertificate)
			{
				http_response_code(400);
				die('x509 parsing failed');
			}
			
			// Check that the domain echo-api.amazon.com is present in
			// the Subject Alternative Names (SANs) section of the signing certificate
			if(strpos($parsedCertificate['extensions']['subjectAltName'],$echoServiceDomain)===false)
			{
				http_response_code(400);
				die('subjectAltName Check Failed');
			}
			
			// Check that the signing certificate has not expired
			// (examine both the Not Before and Not After dates)
			$validFrom=$parsedCertificate['validFrom_time_t'];
			$validTo=$parsedCertificate['validTo_time_t'];
			$time=time();
			if (!($validFrom<=$time && $time<=$validTo))
			{
				http_response_code(400);
				die('certificate expiration check failed');
			}
			
			return true;
		}
		
    /**
    * Checks if the Time of the Timestamp is no larger than 60s
    * @return bool - If the Timestamp is valid
    */
		public function IsValidTime()
		{
			if (time() - strtotime($this->GetTimestamp()) > 60)
			{
				http_response_code(400);
				die("Timestamp is too old!");
			}
			return true;
		}
		
    /**
    * Gets the Timestamp from the Amazon Request
    * @return string - Timestamp for the Request
    */
		public function GetTimestamp()
		{
			return $this->GetRequest()->timestamp;
		}
		
    /**
    * Check if the Chain URL is a valid Amazon URL
    * as per Amazons directive to validate this
    * @param $url - The URL of the Certificate Chain
    */
		public function IsValidKeyURL($url)
		{
			$uriParts = parse_url($url);
			if (strcasecmp($uriParts['host'],'s3.amazonaws.com')!=0)
			{
				http_response_code(400);
				die('The host for the Certificate provided in the header is invalid');
			}
			if (strpos($uriParts['path'],'/echo.api/')!==0)
			{
				http_response_code(400);
				die('The URL path for the Certificate provided in the header is invalid');
			}
			if (strcasecmp($uriParts['scheme'],'https')!=0)
			{
				http_response_code(400);
				die('The URL is using an unsupported scheme. Should be https');
			}
			if (array_key_exists('port',$uriParts) && $uriParts['port']!='443')
			{
				http_response_code(400);
				die('The URL is using an unsupported https port');
			}
			return true;
		}
		
		/**
		 * Routes to the correct Intent for Alexa
		 */
		public function Route()
		{
			require_once("intents/".$this->GetApplicationID()."/index.php");
			if ($this->IsIntent())
			{
				$controller=new IntentController($this->GetResponse(),$this->GetIntent());
				$controller->Route($this->GetIntent()->GetName());
			}
			else
			{
				$controller=new IntentController($this->GetResponse(),null);
				$controller->Route("Launch");
			}
		}
		
		/**
		 * Get the Response Object for settings the Response
		 * @return Response
		 */
		public function GetResponse()
		{
			return $this->response;
		}
		
		/**
		 * Get the Request Part from the Request
		 * @return unknown
		 */
		public function GetRequest()
		{
			if (isset($this->data->request))
			{
				return $this->data->request;
			}
			else
			{
				return null;
			}
		}
		
		/**
		 * Returns the Application ID
		 * @return unknown
		 */
		public function GetApplicationID()
		{
			if (isset($this->data->context->System->application->applicationId))
			{
				return $this->data->context->System->application->applicationId;
			}
			else
			{
				return null;
			}
		}
		
		/**
		 * If the current Request is an Intent. Launch otherwise!
		 * @return boolean
		 */
		public function IsIntent()
		{
			if ($this->GetRequest()!=null)
			{
				return $this->GetRequest()->type=="IntentRequest";
			}
			return false;
		}
		
		/**
		 * Gets the Intent if it was an Intent Request
		 * @return Intent
		 */
		public function GetIntent()
		{
			return new Intent($this->GetRequest()->intent);
		}
		
		/**
		 * Sends the Response
		 */
		public function Send()
		{
			$this->response->Send();
		}
	}

	/**
	 * Generated Speech for Alexa
	 * @author KevinGregull
	 *
	 */
	class Speech
	{
		/**
		 * Holds SSML Alexa Text
		 * @var string
		 */
		private $ssml="";
		
		/**
		 * Construct Speakable text<br><br>
		 * 
		 *  <b>&lt;say-as interpret-as="cardinal"&gt;12345&lt;/say-as&gt;<br></b>
		 *  <b>characters</b>, spell-out:?Spell out each letter.<br>
		 *  <b>cardinal</b>, number: Interpret the value as a cardinal number.<br>
		 *  <b>ordinal</b>:?Interpret the value as an ordinal number.<br>
		 *  <b>digits</b>: Spell each digit separately .<br>
		 *  <b>fraction</b>:?Interpret the value as a fraction. This works for both common fractions (such as 3/20) and mixed fractions (such as 1+1/2).<br>
		 *  <b>unit</b>:?Interpret a value as a measurement. The value should be either a number or fraction followed by a unit (with no space in between) or just a unit.<br>
		 *  <b>date</b>:?Interpret the value as a date. Specify the format with the format attribute.<br>
		 *  <b>time</b>:?Interpret a value such as 1'21" as duration in minutes and seconds.<br>
		 *  <b>telephone</b>:?Interpret a value as a 7-digit or 10-digit telephone number. This can also handle extensions (for example, 2025551212x345).<br>
		 *  <b>address</b>:?Interpret a value as part of street address.<br>
		 *  
		 * @param unknown $text
		 */
		function __construct($text)
		{
			$this->ssml="<speak>".$text."</speak>";
		}
		
		/**
		 * Return parsed Data for the Response
		 * @return string[]
		 */
		public function GetData()
		{
			return ["type"=>"SSML","ssml"=>$this->ssml];
		}
	}
	
	/**
	 * Hold a Card for Alexa App
	 * @author KevinGregull
	 *
	 */
	class Card
	{
		/**
		 * Hold the Titlte
		 * @var string
		 */
		private $title="";
		
		/**
		 * Holds the Text
		 * @var string
		 */
		private $text="";
		
		private $image=null;
		
		/**
		 * Creates a new Notification with Title and Text
		 * @param string $title
		 * @param string $text
		 */
		function __construct($title,$text)
		{
			$this->title=$title;
			$this->text=$text;
		}
		
		public function SetImage($image)
		{
			$this->image=$image;
		}
		
		/**
		 * Return parsed Data for the Response
		 * @return string[]
		 */
		public function GetData()
		{
			if ($this->image==null)
			{
				return ["type"=>"Simple","title"=>$this->title,"content"=>$this->text];
			}
			else
			{
				return ["type"=>"Standard","title"=>$this->title,"content"=>$this->text,"image"=>array("smallImageUrl"=>$this->image,"largeImageUrl"=>$this->image)];
			}
		}
	}

	/**
	 * Outputs a Response for Alexa
	 * @author KevinGregull
	 *
	 */
	class Response
	{
		/**
		 * Holds a speech response
		 * @var Speech
		 */
		private $output=null;
		
		/**
		 * Holds a speech response
		 * @var Speech
		 */
		private $repromt=null;
		
		/**
		 * Hold an Alexa Cars
		 * @var Card
		 */
		private $card=null;
		
		private $keep=false;
		
		/**
		 * Adds Speech Output to Response
		 * @param Speech $speech
		 * @return Response
		 */
		public function AddOutput(Speech $speech)
		{
			$this->output=$speech;
			return $this;
		}
		

		/**
		 * Adds Speech Output to Response
		 * @param Speech $speech
		 * @return Response
		 */
		public function AddCard(Card $card)
		{
			$this->card=$card;
			return $this;
		}
		

		/**
		 * Adds Speech Output to Response
		 * @param Speech $speech
		 * @return Response
		 */
		public function AddPromt(Speech $speech)
		{
			$this->repromt=$speech;
			return $this;
		}
		
		/**
		 * Adds Speech Output to Response
		 * @param Speech $speech
		 * @return Response
		 */
		public function KeepListening()
		{
			if ($this->output===null)
			{
				$this->output=new Speech('<break time="100ms"/>');
			}
			$this->keep=true;
			return $this;
		}
		
		/**
		 * Sends the Response to Alexa
		 */
		public function Send()
		{
			$output=json_encode([
				"version"=>"1.0",
				"response"=>[
					"outputSpeech"=>($this->output!==null)?$this->output->GetData():null,
					"card"=>($this->card!==null)?$this->card->GetData():null,
					"reprompt"=>($this->repromt!==null)?["outputSpeech"=>$this->repromt->GetData()]:null,
					"shouldEndSession"=>($this->repromt!==null || $this->keep)?false:true,
				],
			]);
			echo $output;
		}
	}

	$request=new Request(file_get_contents('php://input'));
	$request->Route();
	$request->Send();
	
