<?php

namespace AppBundle\Service;


class MercanetPayment
{

    // Id du marchand (pour savoir à qui faut transferer les sous)
    private $_merchantId;

    // Clé secrete du marchand
    private $_secretKey;

    // La version de la clé au cas ou le marchand ai du changer de clé vu qu'elle était compromise
    private $_keyVersion;

    // Le montant de la transaction, en centimes (donc faire / 100 pour l'avoir en euros)
    private $_amount;

    // L'adresse de retour pour dès que le client à payer sur le site de Mercanet, qu'il soit redirigé vers la bonne page
    private $_returnUrl;

    /* L'url de la réponse automatique, utile pour récuperer à coup sur l'information par mercanet comme quoi le paiement
    à été effectié */
    private $_automaticResponseUrl;

    // On dit par quel cannal on veut pouvoir payer, ici c'est uniquement par internet
    private $_orderChannel = "INTERNET";

    // Version du json qu'on utilise (je peux pas en dire plus parce que j'en sais pas plus :/)
    private $_interfaceVersion = "IR_WS_2.20";

    /* C'est la référence de la transaction, il est unique au monde et est généré automatiquement par la methode uniqid()
    mise plus bas */
    private $_transactionReference;

    /* Ceci est la code de la devise, c'est à dire le numéro qui représente la monnaie dans laquelle le client va payer,
    ici 978 correspond à l'euros */
    private $_currencyCode = 978;

    // Id du projet pour associer le projet à l'achat fait sur mercanet
    private $_projectId;

    // Adresse Email du client qui achete sur mercanet
    private $_clientEmail;

    public function initialise(int $merchantId, string $secretKey, int $keyVersion, int $amount, string $returnUrl, string $automaticResponseUrl, int $projectId, string $customerEmail)
    {
        // On initialise toutes les variables qu'on a besoin pour faire les appels à Mercanet
        $this->setMerchantId($merchantId);
        $this->setSecretKey($secretKey);
        $this->setKeyVersion($keyVersion);
        $this->setAmount($amount);
        $this->setReturnUrl($returnUrl);
        $this->setAutomaticResponseUrl($automaticResponseUrl);
        $this->setProjectId($projectId);
        $this->setClientEmail($customerEmail);

        // On créer une référence de transaction unique pour le paiement
        $this->_transactionReference = "dmag" . uniqid();

        // On hash les informations sensibles à envoyer à Mercanet
        $seal = $this->hashingSeal();

        /* On récupère toutes les informations que Mercanet nous envoie : Si il y a une erreur, on a la fonction
        checkMercanetReturn plus bas pour arreter le processus */
        $data = $this->dataSend($seal);
        $result = $data[0];
        $info = $data[1];

        $error = $this->checkMercanetReturn($result, $info);

        if($error !== null) {
            return null;
        }

        curl_close($data[2]);

        /* On a reçu toutes les infos de Mercanet et tout est supposé OK, du coup on check juste le status code,
        si il est bon, on peut return le résultat décodé */
        $result_array = json_decode($result);

        if ($result_array->redirectionStatusCode == "00") {
            return $result_array;
        } else {
            return null;
        }
    }

    private function hashingSeal() :string
    {
        /* On récupère la plupart des informations qu'on a pour les envoyer à mercanet afin qu'ils fassent un premier
        check histoire que tout soit bon */
        $dataForSeal = $this->getAmount().
            $this->getAutomaticResponseUrl().
            $this->getCurrencyCode().
            $this->getClientEmail().
            $this->getInterfaceVersion().
            $this->getMerchantId().
            $this->getReturnUrl().
            $this->getOrderChannel().
            $this->getProjectId().
            $this->getTransactionReference();

        /* On hash le tout pour éviter que les gentils pirates informatiques nous récupère toutes les infos
        et puissent changer le montant de la transaction comme ils veulent */
        $dataToSend = utf8_encode($dataForSeal);
        return hash_hmac('sha256', $dataToSend, $this->getSecretKey());
    }

    private function dataSend(string $seal) :array
    {

        // On refait une requete de paiement en format json
        $paymentRequest = '{"amount" : "'.$this->getAmount() . '",
            "automaticResponseUrl" : "'.$this->getAutomaticResponseUrl().'",
            "currencyCode" : "'.$this->getCurrencyCode().'",
            "customerEmail" : "'.$this->getClientEmail().'",
            "interfaceVersion" : "'.$this->getInterfaceVersion().'",
            "keyVersion" : "'.$this->getKeyVersion().'",
            "merchantId" : "'.$this->getMerchantId().'",
            "normalReturnUrl" : "'.$this->getReturnUrl().'",
            "orderChannel" : "'.$this->getOrderChannel().'",
            "orderId" : "'.$this->getProjectId().'",
            "transactionReference" : "'.$this->getTransactionReference().'",
            "seal" : "'.$seal.'"}';

        // On envoie le tout à mercanet via curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://payment-webinit-mercanet.test.sips-atos.com/rs-services/v2/paymentInit" );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
        curl_setopt($curl, CURLOPT_POST, true );
        curl_setopt($curl, CURLOPT_POSTFIELDS, $paymentRequest);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Accept:application/json'));
        curl_setopt($curl, CURLOPT_PORT, 443);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        // On récupère toutes les récompenses de mercanet
        $result = curl_exec($curl);
        $info = curl_getinfo($curl);

        return [$result, $info, $curl];
    }

    private function checkMercanetReturn(string $result, array $info) :?string
    {
        $return = null;

        // On check pour savoir si la variable résultat est vide ou non
        if ((!$result) || (strlen($result) == 0) ) {
            $return = 'MAYDAY MAYDAY Y\'A UN PROBLEME DANS LA MATRICE';
        }

        // On check si le code est différent de 200, si c'est le cas cela signifie qu'il y a un problème dans la requete
        if ($info['http_code'] != 200) {
            $return = 'C\'EST CASSE LA AUSSI, ON A EU UN CODE'.$info['http_code'];
        }

        return $return;
    }

    /**
     * @return int
     */
    private function getMerchantId() :int
    {
        return $this->_merchantId;
    }

    /**
     * @param int $merchantId
     */
    private function setMerchantId(int $merchantId) :void
    {
        $this->_merchantId = $merchantId;
    }

    /**
     * @return string
     */
    private function getSecretKey() :string
    {
        return $this->_secretKey;
    }

    /**
     * @param string $secretKey
     */
    private function setSecretKey(string $secretKey) :void
    {
        $this->_secretKey = $secretKey;
    }

    /**
     * @param int $keyVersion
     */
    private function setKeyVersion(int $keyVersion) :void
    {
        $this->_keyVersion = $keyVersion;
    }

    /**
     * @return int
     */
    private function getKeyVersion() :int
    {
        return $this->_keyVersion;
    }

    /**
     * @return int
     */
    private function getAmount() :int
    {
        return $this->_amount;
    }

    /**
     * @param int $amount
     */
    private function setAmount(int $amount) :void
    {
        $this->_amount = $amount;
    }

    /**
     * @return string
     */
    private function getReturnUrl() :string
    {
        return $this->_returnUrl;
    }

    /**
     * @param string $returnUrl
     */
    private function setReturnUrl(string $returnUrl) :void
    {
        $this->_returnUrl = $returnUrl;
    }

    /**
     * @return string
     */
    private function getOrderChannel() :string
    {
        return $this->_orderChannel;
    }

    /**
     * @return string
     */
    private function getInterfaceVersion() :string
    {
        return $this->_interfaceVersion;
    }

    /**
     * @return string
     */
    private function getTransactionReference() :string
    {
        return $this->_transactionReference;
    }

    /**
     * @return int
     */
    private function getCurrencyCode() :int
    {
        return $this->_currencyCode;
    }

    /**
     * @return string
     */
    public function getAutomaticResponseUrl() :string
    {
        return $this->_automaticResponseUrl;
    }

    /**
     * @param string $automaticResponseUrl
     */
    public function setAutomaticResponseUrl(string $automaticResponseUrl): void
    {
        $this->_automaticResponseUrl = $automaticResponseUrl;
    }

    /**
     * @return int
     */
    public function getProjectId() :int
    {
        return $this->_projectId;
    }

    /**
     * @param int $clientEmail
     */
    public function setProjectId(int $projectId): void
    {
        $this->_projectId = $projectId;
    }

    /**
     * @return mixed
     */
    public function getClientEmail() :string
    {
        return $this->_clientEmail;
    }

    /**
     * @param string $clientEmail
     */
    public function setClientEmail(string $clientEmail): void
    {
        $this->_clientEmail = $clientEmail;
    }
}
