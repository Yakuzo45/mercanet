<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Purchase;
use AppBundle\Service\MercanetPayment;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Response;


class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @param Request $request
     * @param MercanetPayment $mercanetPayment
     * @return Response
     */
    public function indexAction(Request $request, MercanetPayment $mercanetPayment)
    {
        $merchantId = $this->container->getParameter('mercanet_merchant_id');
        $secretKey = $this->container->getParameter('mercanet_secret_key');
        $keyVersion = $this->container->getParameter('mercanet_key_version');

        if ($_POST) {
            $data = $_POST['Data'];
            $mercanetInformation = explode('|', $data);
            $tidyMercanetField = [];
            foreach ($mercanetInformation as $key => $mercanetField) {
                $temporaryArray = explode('=',$mercanetField);
                $tidyMercanetField[$temporaryArray[0]] = $temporaryArray[1];

            }
            $this->afterPayment($tidyMercanetField);
        }
        $amount = 500;
        $returnUrl = 'http://127.0.0.1:8000/';
        $automaticResponseUrl = 'https://dev-dmag-test.fr/drawing/';
        $projectId = 2;
        $customerEmail = "tom.guibard@outlook.fr";
        $result_array = $mercanetPayment->initialise($merchantId, $secretKey, $keyVersion, $amount, $returnUrl, $automaticResponseUrl, $projectId, $customerEmail);

        return $this->render('default/index.html.twig', [
            'redirection_url' => $result_array->redirectionUrl,
            'redirection_version' => $result_array->redirectionVersion,
            'redirection_data' => $result_array->redirectionData,
        ]);
    }

    // On hydrate la BDD avec les informations reçus de mercanet
    private function afterPayment(array $tidyMercanetField) :bool
    {
        $purchase = new Purchase();
        $purchase->setDatePurchased(new \DateTime('now'));
        $purchase->setCurrency('EUR');
        $purchase->setAmountPaid($tidyMercanetField['amount']/100);
        $purchase->setTransaction($tidyMercanetField['transactionReference']);
        $purchase->setState('trans_order_status_validated');
        $purchase->setAppointment(false);
        $purchase->setIsDelivered(false);

        $em = $this->getDoctrine()->getManager();
        $em->persist($purchase);
        $em->flush();

        return true;
    }
}
