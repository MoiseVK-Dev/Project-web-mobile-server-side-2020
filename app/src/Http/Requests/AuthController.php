<?php

    class AuthController extends BaseController {

        public function showLoginRegister(){
            $email = '';

            if(isset($_SESSION['user'])){
                $this->returnToOverview('home');
            }

            if(isset($_COOKIE['email'])){
                $email = $_COOKIE['email'];
            }
            if(isset($_GET['email'])){
                $email = $_GET['email'];
            }

            echo $this->twig->render('/pages/login.twig', [
                'error' => isset($_GET['error']),
                'email' => $email
            ]);
        }

        public function login() {
            $email = isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';

            if (isset($_POST['moduleAction']) && ($_POST['moduleAction'] == 'login')) {

                $stmt = $this->db->prepare('SELECT * FROM users INNER JOIN user_data ON users.user_id = user_data.user_id WHERE email = ?');
                $stmt->execute([$email]);
                $userData = $stmt->fetchAllAssociative();

                if(count($userData) > 0 && password_verify($password, $userData[0]['password']) == true){
                    $_SESSION['user'] = $email;
                    $_SESSION['user_id'] = $userData[0]['user_id'];
                    $_SESSION['firstName'] = $userData[0]['name'];

                    if (isset($_POST['remember'])) {
                        setcookie('email', $email, time() + 60 * 60 * 24 * 30);
                    }
                    $this->returnToOverview('home');

                } else {
                    $this->returnToOverview('login?error=true&email=' . $email);
                }

            }
        }

        private function equalPasswords(string $password, string $repeatPassword) : bool {
            if($password == $repeatPassword){
                return true;
            } else {
                return false;
            }
        }

        public function register(){
            $email = isset($_POST['email']) && filter_var($_POST['email'], FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
            $repeatPassword = isset($_POST['repeatPassword']) ? trim($_POST['repeatPassword']) : '';

            if($email && $password && $this->equalPasswords($password, $repeatPassword) == true && isset($_POST['moduleAction']) && ($_POST['moduleAction'] == 'register')){
                $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
                $stmt->execute([$email]);
                $userData = $stmt->fetchAllAssociative();

                if(count($userData) == 0){
                    $data = [$email, password_hash($password, PASSWORD_BCRYPT)];
                    $stmt = $this->db->prepare('INSERT INTO users(email, password) VALUES(?,?)');
                    $stmt->execute($data);


                    //autoLogin after register
                    $_SESSION['user'] = $email;
                    $this->returnToOverview('home');
                } else {
                    $this->returnToOverview('register?error=true&email=' . $email);
                }
            } else {
                $this->returnToOverview('register?error=true&email=' . $email);
            }
        }

        public function logout(){
            if (isset($_SESSION['user'])) {
                session_start();
                $_SESSION = [];
                session_destroy();
                header('Location: /home');
                exit();
            }
            else{
                header('Location: /login');
                exit();
            }
        }
    }