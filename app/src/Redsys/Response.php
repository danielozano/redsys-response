<?php
namespace Redsys;

use Exception;
use Redsys\Api\RedsysAPI as Redsys;
use Redsys\Curl;

class Response
{
    private $options = array();
    /**
     * Objeto que contiene la API de redsys para poder utilizar sha256 de forma cómoda
     * @var RedsysAPI
     * @author  Daniel Lozano Morales dn.lozano.m@gmail.com
     */
    private $redsys;
    /**
     * Determinar si el TPV es viejo o nuevo (sha256)
     * @var string
     * @author  Daniel Lozano Morales dn.lozano.m@gmail.com
     */
    private $sha256;

    public function __construct(array $options)
    {
       /**
        * Comprobar si petición para sha256 o tpv antiguo
        * 
        * @author  Daniel Lozano Morales dn.lozano.m@gmail.com
        * @todo Hacerlo comprobando la versión de la firma.
        */
        isset($_POST['Ds_MerchantParameters']) ? $this->sha256 = true : $this->sha256 = false;
        
        $this->setOption($options);

        return $this;
    }

    public function setOption($option, $value = null)
    {
        if (is_string($option)) {
            $option = array($option => $value);
        }

        $this->options = array_merge($this->options, $option);

        return $this;
    }

    public function getOption($key = null, $default = '')
    {
        if (empty($key)) {
            return $this->options;
        } elseif (array_key_exists($key, $this->options)) {
            return $this->options[$key];
        } else {
            return $default;
        }
    }

    public function loadFromUrl()
    {
        $path = basename(preg_replace('#/$#', '', getenv('REQUEST_URI')));
        // Alomejor trae parámetros y sólo nos interesa la primera parte
        $path = explode("?", $path);
        if (!$this->isValidPath($path[0])) {
            die('Invalid Path');
        }

        try {
            /**
             * Si no es sha256, seguir el workflow antiguo.
             * Es mejor no refactorizar nada de momento.
             *
             * @author  Daniel Lozano Morales dn.lozano.m@gmail.com
             */
            if ($this->sha256)
            {
                $this->realizarPagoSha256();
            }
        } catch (Exception $e) {}
    }

    private function isValidPath($path)
    {
        return in_array($path, array('realizarPago'), true);
    }
    /**
     * Simular respuesta a un pago realizando mediante un TPV Redsys con la nueva
     * clave sha256
     * 
     * @author  Daniel Lozano Morales dn.lozano.m@gmail.com
     */
    public function realizarPagoSha256 ()
    {
        // firma recibida desde el cliente
        $firmaRecibida = $_POST['Ds_Signature'];
        $params = $_POST["Ds_MerchantParameters"];

        $this->redsys = new Redsys();
        $datos = $this->redsys->decodeMerchantParameters($params);

        $sha256key = $this->getOption('256key');
        $firmaGenerada = $this->redsys->createMerchantSignatureNotif($sha256key, $params);
        /**
         * Comprobar si queremos transición exitosa o errónea
         * 
         * @author Daniel Lozano dn.lozano.m@gmail.com
         */
        $estadoPedido = $this->getOption('status', 'autorizada');
        $success = true;
        $codigoRespuesta = 99;

        $urlBack = $this->redsys->getParameter('DS_MERCHANT_URLOK');
        if (empty($estadoPedido))
        {
            die('Necesitas indicar un estado para el pedido');
        }

        if ($estadoPedido === 'sin finalizar' || $estadoPedido === 'canelada')
        {
            die(header('Location: ' . $this->redsys->getParameter('DS_MERCHANT_URLKO')));
        }

        if ($estadoPedido == 'denegada')
        {
            $success = false;
            $codigoRespuesta = $this->getOption('errorCode', 101);
            $urlBack = $this->redsys->getParameter('DS_MERCHANT_URLKO');
        }
        /**
         * Generar Array de Datos
         *
         * @author  Daniel Lozano dn.lozano.m@gmail.com
         */
        $post = array(
            'Ds_Amount' => $this->redsys->getParameter('DS_MERCHANT_AMOUNT'),
            'Ds_Currency' => $this->redsys->getParameter('DS_MERCHANT_CURRENCY'),
            'Ds_Order' => $this->redsys->getParameter('DS_MERCHANT_ORDER'),
            'Ds_MerchantCode' => $this->redsys->getParameter('DS_MERCHANT_MERCHANTCODE'),
            'Ds_Terminal' => $this->redsys->getParameter('DS_MERCHANT_TERMINAL'),
            'Ds_TransactionType' => 0,
            'Ds_ConsumerLanguage' => $this->redsys->getParameter('Ds_Merchant_ConsumerLanguage')
        );
        // Objeto para generar respuesta encriptada
        $redsysResponse = new Redsys();
        // Establecer url de vuelta

        foreach($post as $key => $value)
        {
            $redsysResponse->setParameter($key, $value);
        }

        $redsysResponse->setParameter('Ds_Response', $codigoRespuesta);

        $codigoAuthorizacion = ($success ? mt_rand(100000, 999999) : '');
        $redsysResponse->setParameter('Ds_AuthorisationCode', $codigoAuthorizacion);

        $response = array();
        $response['Ds_MerchantParameters'] = $redsysResponse->createMerchantParameters();
        $response['Ds_Signature'] = $redsysResponse->createMerchantSignatureNotif($this->getOption('256key'), $response['Ds_MerchantParameters']);

        $curl = new Curl(array(
                'base'  => $this->redsys->getParameter('DS_MERCHANT_MERCHANTURL')
            )
        );

        if (isset($_GET['asynchronous']) && $_GET['asynchronous'])
        {
            $curl->post('', array(), $response);

            sleep(1);

            die(header('Location: ' . $urlBack));
        }
        else 
        {
            $form = "
                <form action=$urlBack method=POST name='frm'>
                    <input type='hidden' name='Ds_Signature' value=" . $response['Ds_Signature'] . ">
                    <input type='hidden' name='Ds_MerchantParameters' value=" . $response['Ds_MerchantParameters'] . ">
                </form>
                <script languaje='javascript'>
                    document.frm.submit();
                </script>
            ";
            echo $form;
        }
    }
}
