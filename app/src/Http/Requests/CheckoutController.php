<?php

    class CheckoutController extends BaseController {
        public function showCheckout() {
            echo $this->twig->render('pages/checkout.twig' , [
                'firstname' => isset($_SESSION['firstName']) ? $_SESSION['firstName'] : '',

                'fName' => isset($_POST['fName']) ? $_POST['fName'] : '',
                'lastName' => isset($_POST['lastName']) ? $_POST['lastName'] : '',
                'email' => $_SESSION['user'],
                'address' => isset($_POST['address']) ? $_POST['address'] : '',
                'number' => isset($_POST['number']) ? $_POST['number'] : '',
                'appNumber' => isset($_POST['appNumber']) ? $_POST['appNumber'] : '',
                'city' => isset($_POST['city']) ? $_POST['city'] : '',
                'postal' => isset($_POST['postal']) ? $_POST['postal'] : '',
                'country' => isset($_POST['country']) ? $_POST['country'] : '',

                'countries' => $this->getCountries(),

                'personalDetails' => $this->persData,
                'paymentDetails' => $this->finData,

                'paymentMethod' => ''
            ]);
        }

        private $persData = false;
        private $finData = false;

        private $fName  = '';
        private $lastName = '';
        private $email = '';
        private $address = '';
        private $number = '';
        private $appNumber = '';
        private $city = '';
        private $postal = '';
        private $country = '';
        private $tickets = '';


        public function processPersonalInformation() {
            $this->fName = isset($_POST['fName']) ? $_POST['fName'] : '';
            $this->lastName = isset($_POST['lastName']) ? $_POST['lastName'] : '';
            $this->email = isset($_POST['email']) ? $_POST['email'] : '';
            $this->address = isset($_POST['address']) ? $_POST['address'] : '';
            $this->number = isset($_POST['number']) ? $_POST['number'] : '';
            $this->appNumber = isset($_POST['appNumber']) ? $_POST['appNumber'] : '';
            $this->city = isset($_POST['city']) ? $_POST['city'] : '';
            $this->postal = isset($_POST['postal']) ? $_POST['postal'] : '';
            $this->country = isset($_POST['country']) ? $_POST['country'] : '';

            if($this->fName && $this->lastName && $this->email && $this->address && $this->number && $this->city && $this->postal && $this->country){
                $this->persData = true;
            } else {
                $this->persData = false;
            }
            $this->showCheckout();
        }

        public function processFinancial () {
            $this->finData = true;
            //ADD DATA TO WRITE IN DB HERE CONVERT TO TICKET
            $tickets = isset($_POST['finalShoppingCart']) ? $this->getTicketsShoppingBag(explode(',', $_POST['finalShoppingCart'])) : [];
            $this->addMultipleSalesToDB([$tickets]);
            $this->showThankyou();
        }

        public function showThankyou() {
            echo $this->twig->render('pages/thankyou.twig', [
                'success' => true,
                'firstname' => $_SESSION['firstName']
            ]);
        }

        private function addMultipleSalesToDB(array $tickets) {
            foreach($tickets[0] as $ticket){
                $this->addSaleToDb($ticket['seller_id'], $ticket['ticket_id']);
            }
        }

        private function addSaleToDb(int $seller_id, int $ticket_id) {
            $stmt = $this->db->prepare('INSERT INTO transactions(date_transaction, seller_id, buyer_id, ticket_id) VALUES (?,?,?,?)');
            $stmt->execute([
                date('Y-m-d H:i:s'),
                $seller_id,
                $_SESSION['user_id'],
                $ticket_id
            ]);
            $transactionID = $this->db->lastInsertId();
            $this->updateTicket($transactionID, $ticket_id);
            $this->docGenerator->genDocument($transactionID);

            sleep(5);
            $this->sendMail($ticket_id . '.pdf', $transactionID . '.txt');
        }

        private function updateTicket(int $transactionID, int $ticketID) {
            $stmt = $this->db->prepare('UPDATE tickets SET transaction_id = ? WHERE ticket_id = ?');
            $stmt->execute([
                $transactionID,
                $ticketID
            ]);
        }

        public function showCart() {
            $data = isset($_POST['cartTickets']) && $_POST['moduleAction'] == 'shoppingCart' ? $this->getTicketsShoppingBag(explode(',' , $_POST['cartTickets'])) : '';
            echo $this->twig->render('pages/shoppingCart.twig', [
                'firstname' => $_SESSION['firstName'],
                'tickets' => $data
            ]);
        }

        private function getTicketsShoppingBag(array $ticketIds) : array {
            $ticketDetails = [];
            if($ticketIds[0] != ''){
                for($i = 0; $i < count($ticketIds); $i++) {
                    //$ticketDetails[] = $this->convertResultToModels($this->getTicketDetails($ticketIds[$i]));
                    $ticketDetails[] = $this->getTicketDetails($ticketIds[$i]);
                }
                return $ticketDetails;
            } else {
                return [];
            }
        }

        protected function getTicketDetails(int $id) : array {
            $stmt = $this->db->prepare('SELECT *, user_data.name as firstName, events.name as event_name FROM tickets INNER JOIN events ON tickets.event_id=events.event_id INNER JOIN user_data ON tickets.seller_id = user_data.user_id WHERE tickets.ticket_id = ?');
            $stmt->execute([$id]);
            return $stmt->fetchAllAssociative()[0];
        }

        protected function convertResultToModels(array $result) : array {
            return [
                $this->createUserModel($result),
                $this->createTicketModel($result),
                $this->createEventModel($result)
            ];
        }

        protected function createUserModel(array $result) : User {
             return new User(
                $result['user_id'],
                $result['firstName'],
                $result['last_name'],
                $_SESSION['user'],
                new Address(
                    $result['address'],
                    $result['city'],
                    $result['country']
                ),
                $result['friends_invited'],
                $result['tickets_sold'],
                [],
                $result['tickets_bought'],
                []
            );
        }

        protected function createTicketModel (array $result) : Ticket {
            return new Ticket(
                $result['ticket_id'],
                $result['name'],
                $result['ticket_price'],
                $result['amount'],
                $result['sale_reason'],
                $result['event_id'],
                $result['seller_id'],
                $result['firstName'] . ' ' . $result['last_name'],
                $result['ticket_type']
            );
        }

        protected function createEventModel(array $result) : Event {
            return new Event(
                $result['event_id'],
                $result['event_name'],
                $result['ticketprice_standard'],
                $result['location'],
                $result['description'],
                $result['begin_time'],
                $result['end_time']
            );
        }

        private function sendMail (string $ticketPath, string $transactionPath): void {
                $this->mailer->sendTicketMail([$_SESSION['user']], $this->composeMailText($_SESSION['firstName']), ['ticket' => $ticketPath, 'transaction' => $transactionPath] , 'Uw tickets van TickSwap');
        }

        private function composeMailText(string $fname) : string{
            $message = '<main>';
            $message .= '<h2>TicketSwap</h2>';
            $message .= '<p>Beste ' . $fname . '</p>';
            $message .= '<p>U heeft zojuist tickets gekocht via www.TicketSwap.be </p>';
            $message .= '<p>In de bijlage van het document vind u de tickets en een bewijs dat deze legaal overgekocht zijn.</p>';
            $message .= '<p>Met vriendelijke groet</p> <p>het Ticketswap team</p>';
            $message .= '<br><br><p>TicketSwap</p>';
            $message .= '</main>';

            return $message;
        }
    }