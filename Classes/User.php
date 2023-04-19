<?php

class User extends CRUD {
    public function create($data){
        try {    
            $this->connect();
            $userValidation = new UserValidator($data, "create");
            
            if( $userValidation->isValid()){
                $target_file = "../assets/Images/" . basename($_FILES["avatar"]["name"]);  
                move_uploaded_file($_FILES["avatar"]["tmp_name"],__DIR__ . '/' . $target_file);
                $data['avatar'] = basename($_FILES["avatar"]["name"]);
                $data['mobile'] = "+2".$data['mobile'];
                $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
                $this->save($data);
            }else{
                $_SESSION['data'] = $data;
                $this->showError($userValidation->getError());
            }
        
        }catch(Exception $e) {
            new Log($this->log_file, $e->getMessage());
            return false;
        }
    }

    private function showError($error) {
        foreach ($error as $key => $value) {
            if (!empty($value)){
                echo "<script>document.getElementById('$key').innerHTML = '$value';</script>";
            }
        }
    }

    private function assignRole($email,$group){
        try {
            switch($group){
                case 1:
                {
                    $this->_auth->admin()->addRoleForUserByEmail($email, \Delight\Auth\Role::ADMIN);
                    break;
                } 
                case 2:
                {
                    $this->_auth->admin()->addRoleForUserByEmail($email, \Delight\Auth\Role::EDITOR);
                    break;
                } 
                case 3:
                default:
                $this->_auth->admin()->addRoleForUserByEmail($email, \Delight\Auth\Role::REVIEWER);
            }
            
        }
        catch (\Delight\Auth\InvalidEmailException $e) {
            die('Unknown email address');
        }
    }

    public function save($data){
        try{
            $this->connect();
            $username = $data['username'];
            $email = $data['email'];
            $avatar = $data['avatar'];
            $groupID = $data['gid'];
            $mobile = $data['mobile'];
            $password = $data['password'];
            $subscriptionDate = date('Y-m-d H:i:s'); 
            $table = 'users';

            $sql = "insert into `$table` (email, password,username,registered,last_login,subscription_date,avatar,mobile,gid) 
            values ('$email', '$password', '$username',  '$subscriptionDate ', '$subscriptionDate ','$subscriptionDate ', '$avatar', '$mobile' , '$groupID');";
            if (mysqli_query($this->_dbHandler, $sql)) {
                $id = mysqli_insert_id($this->_dbHandler);
                $this->assignRole($email,$groupID);
                $this->disconnect();
                header("Location:/users");
                return $id;
            } else {
                $this->disconnect();
                ob_end_flush();
                header("Location: /users");
    
                return false;
            }
        }catch(Exception $e){
            new Log($this->log_file, $e->getMessage());
            return false;
        }
    }

    public function edit(){
        try{
            $avatar = $_FILES['avatar']['name'];
            $id = $_GET['id'];
            $user = $this->search(Array('column'=> 'id', 'value' => $id));

            $edited_values = array(
                'id' => $id,
                'username' => $_POST['name'],
                'email' => $_POST['email'],
                'mobile' => $_POST['mobile'],
                'password' => $_POST['password'],
                'gid' => $user[0]['gid'],
                'avatar' => $avatar
            );
            
            if(! is_null($avatar)){
                $target_file = "../assets/Images/" . basename($_FILES["avatar"]["name"]);  
                move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/' . $target_file);
                $avatar = basename($_FILES["avatar"]["name"]);
            }else{
                if(! is_null($user[0]['avatar'])){
                    $edited_values['avatar'] = $user[0]['avatar'];
                }
            }

            $userValidation = new UserValidator($edited_values, "update");

            if( $userValidation->isValid()){
                $edited_values['password'] = password_hash( $_POST['password'], PASSWORD_DEFAULT);
                // $update_group = $this->update($edited_values , $id);
                header('location: /users');
            }else{
                $_SESSION['data'] = $edited_values;
                $_SESSION['errors'] = $userValidation->getError();
                header("location: /users/edit/?id=$id");
            }

        } catch(Exception $e) {
            new Log($this->log_file, $e->getMessage());
            return false;
        } 
    }
    
    public function filterUsersByGroup($groupName){
        try{
            $this->connect();
            $stmt = $this->_dbHandler->prepare("SELECT * FROM users INNER JOIN groups ON users.gid = groups.gid WHERE groups.gname = ?");
            $stmt->bind_param("s", $groupName);
            $stmt->execute();
            $result = $stmt->get_result();
            $users = array();
            while ($user = mysqli_fetch_assoc($result)) {
                $users[] = $user;
            }
            return $users;
        }
        catch(Exception $e) {
            new Log($this->log_file, $e->getMessage());
            return false;
        }
        
    }
    
    public function getCount ($table){
        $sql = "select * from `$table` ";
        $this->connect();
        $_handler_results = mysqli_query($this->_dbHandler, $sql);
        $rowcount=mysqli_num_rows($_handler_results);
        return $rowcount;
    }

    public function logout() {
        $_SESSION = array(); // reset session array
        session_destroy(); 
        setcookie("remember_token", "", time() - 3600);  // destroy session     
        header('Location: /login');
        exit;
    }
}
?>