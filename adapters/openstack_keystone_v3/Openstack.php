 <?php
class openstack
{
  var $main_ch = null;
  var $cookiefile = "/tmp/cookies.txt";
  var $user;
  var $pwd;
  var $controller_ip;
  var $port;

  function __construct($user, $pwd, $controller_ip, $port)
  {
    $this->user = $user;
    $this->pwd = $pwd;
    $this->controller_ip = $controller_ip;
    $this->port = $port;
  }

  //after login, global $main_ch is set. pls use it for post requests. do not forget to close it also
  function login()
  {
    $controller = $this->controller_ip;
    $port = $this->port;
    $cookiefile = $this->cookiefile;
    $user = $this->user;
    $pwd = $this->pwd;

    // ######## Get OpendDaylight Web page
    $url = "http://$controller:$port/";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // get headers too with this line
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $result = curl_exec($ch);
    // get cookie
    preg_match('/^Set-Cookie:\s*([^;]*)/mi', $result, $m);
    parse_str($m[1], $cookies);
    $jsessionid = $cookies["JSESSIONID"];

    //######## Athentification to openstack Controller Web page
    $url = "http://$controller:$port/j_security_check";

    $post = "j_username=$user&j_password=$pwd";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Cookie: JSESSIONID=$jsessionid"
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $result = curl_exec($ch);

    //######## Get openstack Controller main page
    $url = "http://$controller:$port/";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        "Cookie: JSESSIONID=$jsessionid"
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiefile);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $result = curl_exec($ch);

    //######## Get Topology config
    $url = "http://$controller:$port/controller/web/devices/nodesLearnt";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiefile);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $result = curl_exec($ch);

    return $result;
  }
}

?>