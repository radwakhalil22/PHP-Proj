<?php 

use Twilio\Rest\Client;

class Auth{
  public $id;
  public $auth;

  public $pdo ; 
  private $log_file="loginError.log";

  public function __construct(){
    $this->authConnection();
  }

  function authConnection() {
    $dsn = 'mysql:host=' . _HOST_ . ':'. _PORT_ . ';dbname=' . _DB_NAME_ .'';
    try{
        $pdo = new PDO($dsn, _USER_, _PASSWORD_); 
    }catch(PDOException $e){
        die($e->getMessage());
    }
    $this->auth = new \Delight\Auth\Auth($pdo);
  }

  public function login(){
    try {
      if ( isset($_POST['remember_me'])) {
        $rememberDuration = (int) (60 * 60 * 24 * 365.25);
      }
      else {
          $rememberDuration = null;
      }
        $this->auth->login($_POST['email'], $_POST['password'],$rememberDuration);
        $this->getLastVisit();
        echo "<script>setTimeout(\"location.href = 'home';\",1500);</script>";
        return true ; 
    }

    catch (\Delight\Auth\InvalidEmailException $e) {
      new Message('Wrong email address');
      return false ; 
    }

    catch (\Delight\Auth\InvalidPasswordException $e) {
      new Message('Wrong password');
      return false ; 
    }

    catch (\Delight\Auth\EmailNotVerifiedException $e) {
      new Message('Email not verified');
      return false ; 
    }

    catch (\Delight\Auth\TooManyRequestsException $e) {
      new Message('Too many requests');
      return false ; 
    }
  }

  public function checkLoggedIn() {
    if($this->auth->isLoggedIn()){
      require_once("./views/dashboard.php");
    }
  }

  public function getLastVisit(){
    $id = $this->auth->getUserId();
    $stmt = $this->pdo->prepare('SELECT last_login FROM users WHERE id = ?' );
    $stmt->bind_param('i' , $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $last_login = $result['last_login'];
    $last_login = date("Y-m-d H:i:s", $last_login);
    
    if($last_login) {
        new Message("Hello and welcome back! We hope you've been well since your last visit on $last_login");
    } else {
        new Message("Welcome! This is your first visit.");
    }
    // new Message("Hello and welcome back! We hope you've been well since your last visit on $last_login");
  }

  public function resetPassword($userEmail){
    $users = new User('users', "UsersErrors.log",'id');
    $user = $users->search(array("column" => "email", "value" => $userEmail));
    
    if(!empty($user)){
      $user = $user[0];
      $userMobile = $user["mobile"];

      $client = new Client(_ACCOUNT_SID_, _AUTH_TOKEN_);
      $sentOTP = mt_rand(100000, 999999);
      $message = "Hello " . $user["username"] . " your Verification OTP code is: " . $sentOTP;
      
      try {
        $message = $client->messages->create(
        $userMobile,
        array(
            'body' => $message,
            'from' => _SENDER_
          )
        );
        
        setcookie('otp', $sentOTP);
        return true;
      } catch (Exception $e) {
        die('Error: ' . $e->getMessage());
      }
    }
    return false;
  }

  public function verifyOTP($otp){
    $sentOTP = $_COOKIE['otp'];
    if($sentOTP == $otp){
      return true;
    }
    return false;
  }

  public function changePassword($userEmail ,$password, $confirmedPass){
    if($password === $confirmedPass) {
        $users = new User('users', "UsersErrors.log",'id');
        $user = $users->search(array("column" => "email", "value" => $userEmail));

        $data = [
          'username' => $user[0]['username'],
          'email' => $user[0]['email'],
          'gid' => $user[0]['gid'],
          'mobile' => $user[0]['mobile'],
          'password' => password_hash($password, PASSWORD_DEFAULT),
          'avatar' => $user[0]['avatar'],
          'deleted_at' => $user[0]['deleted_at'],
          'subscription_date' => $user[0]['subscription_date'],
        ];

        $users->update($data, $user[0]['id']);
        return true;
    }
    return false;unset($_SESSION['data']);
  }
}