<?php
namespace Redsys;

use Exception;
use Redsys\Api\RedsysAPI as Redsys;
use Redsys\Curl;

class Response
{
    /**
     * Opciones de configuración del TPV Redsys
     * 
     * @var array
     */
    private $options = array();
    /**
     * String de parámetros recibidos (codificados) Ds_MerchantParams
     * 
     * @var string
     */
    private $dsMerchantParams;
    /**
     * String que contiene el parámetro recibido: Ds_Version
     * 
     * @var string
     */
    private $dsVersion;
    /**
     * String formado por la firma recibida: Ds_Signature
     * 
     * @var String
     */
    private $dsSignature;
    /**
     * String formado por la firma generada en el TPV Redsys
     * 
     * @var string
     */
    private $localSignature;
    /**
     * Objeto que contiene la API de redsys para poder utilizar sha256 de forma cómoda
     * @var RedsysAPI
     * @author  Daniel Lozano Morales dn.lozano.m@gmail.com
     */
    private $redsys;
    private $redsysApi;
    /**
     * Determinar si el TPV es viejo o nuevo (sha256)
     * @var string
     * @author  Daniel Lozano Morales dn.lozano.m@gmail.com
     */
    private $sha256;

    public function __construct(Redsys $redsysApi, array $options, $postData = null)
    {
        $this->initParameters($postData);
        $this->setOption($options);

        return $this;
    }
    /**
     * Inicializa los valores de los parámetros de nuestra clase
     * 
     * @param  array|string $postData Datos recibidos por POST
     * @return $this
     */
    public function initParameters($postData)
    {
        $errors = array();
        if (empty($postData['Ds_SignatureVersion']))
        {
            $errors[] = 'Falta parámetro: Ds_SignatureVersion';
        }

        if (empty($postData['Ds_MerchantParameters']))
        {
            $errors[] = 'Falta parámetro: Ds_MerchantParameters';
        }

        if (empty($postData['Ds_Signature']))
        {
            $errors[] = 'Falta parámetro: Ds_Signature';
        }

        if (count($errors) > 0)
        {
            $errorString = implode(', ', $errors);
            echo "$errorString </br>";
            throw new Exception(" Error al recibir parámetros");
        }

        $this->setDsSignatureVersion($postData['Ds_SignatureVersion']);
        $this->setDsSignature($postData['Ds_Signature']);
        $this->setDsMerchantParams($postData['Ds_MerchantParameters']);
    }

    private function setDsSignatureVersion($signatureVersion)
    {
        $this->dsVersion = $signatureVersion;
    }

    private function setDsSignature ($signature)
    {
        $this->dsSignature = $signature;
    }

    private function setDsMerchantParams($params)
    {
        $this->dsMerchantParams = $params;
    }

    public function getDsSignatureVersion()
    {
        return $this->dsVersion;
    }

    public function getDsSignature()
    {
        return $this->dsSignature;
    }

    public function getDsMerchantParams()
    {
        return $this->dsMerchantParams;
    }

    /****   REHACER DESDE AQUÍ ****/






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

        if (!$this->isValidPath($path)) {
            die('Invalid Path');
        }

        try {
            /**
             * Si no es sha256, seguir el workflow antiguo.
             * Es mejor no refactorizar nada de momento.
             *
             * @author  Daniel Lozano Morales dn.lozano.m@gmail.com
             */
             $this->realizarPagoSha256();
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
        var_dump($this->getDsMerchantParams());
        die();
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
/**
 * Posibles propiedades
 *
 * Parámetros recibidos:
 *  - merchantParams
 *  - signature (firma recibida)
 *  - dsversion
 *
 * Parámetros configurados
 *  - tpvoptions
 *  - firma generada
 *  
 * Dependencias: opciones, parámetros $_POST
 * 
 * setParameter para establecer el vaor de un parámetro recibido
 * getParameter para obtener el valor de un parámetro recibido
 *
 * setOption para establecer valor opción de tpv
 * getOption para obtener valor opción de tpv
 *
 * checkSignature para comprobar que las firmas coinciden
 *
 * Necesidad de método para generar respuesta (decidir nombre)
 * Posible necesidad de un método para generar respuesta síncrona, con formulario.
 */