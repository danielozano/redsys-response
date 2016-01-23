<?php
namespace Redsys;

use Exception;
use Redsys\Api\RedsysAPI;
use Redsys\Curl;
/**
 * @todo: añadir documentación
 */
class Response
{
    private $dsParameters;

    private $merchantParamsArray;

    private $response;

    private $error = false;

    protected $redsysApi;

    public $options;
    /**
     * @param RedsysAPI $redsysApi [description]
     * @param array     $options   [description]
     */
    public function __construct(RedsysAPI $redsysApi, array $options)
    {
        $this->redsysApi = $redsysApi;
        $this->setOptions($options);

        // comprobar que el TPV esta configurado correctamente
        if (!$this->checkOptions()) {
            return $this;
        }

        // iniciar parámetros recibidos
        $this->setDsParams();

        // crear objeto respuesta
        $this->response = new RedsysAPI();

        // datos del objeto respuesta
        $responseParameters = array(
            'Ds_Amount' => $this->getMerchantParam('DS_MERCHANT_AMOUNT'),
            'Ds_Order' => $this->getMerchantParam('DS_MERCHANT_ORDER'),
            'Ds_MerchantCode' => $this->getMerchantParam('DS_MERCHANT_MERCHANTCODE'),
            'Ds_Terminal' => $this->getMerchantParam('DS_MERCHANT_TERMINAL'),
            'Ds_TransactionType' => 0
        );

        // rellenar objeto respuesta
        foreach ($responseParameters as $key => $value) {
            $this->response->setParameter($key, $value);
        }
    }

    /** inicializar los parámetros recibidos */
    private function setDsParams()
    {
        $errorMapping = array(
            'Ds_SignatureVersion'   => 'SIS0429',
            'Ds_MerchantParameters' => 'SIS0430',
            'Ds_Signature'          => 'SIS0435'
        );
        $dsKeys = array('Ds_SignatureVersion', 'Ds_Signature', 'Ds_MerchantParameters');
        if (empty($_POST)) {
            throw new Exception("No se han recibido datos", 1);
        }
        foreach ($dsKeys as $key) {
            if (!array_key_exists($key, $_POST)) {
                $this->setError($errorMapping[$key]);
                // throw new Exception("No se ha recibido el siguiente parámetro: $key", 1);
            }
            $this->dsParameters[$key] = $_POST[$key];
        }
    }
    /** obtener uno de los tres parámetros recibidos */
    public function getParam($key)
    {
        if (!array_key_exists($key, $this->dsParameters)) {
            return;
        }
        return $this->dsParameters[$key];
    }
    /** obtener un sólo parámetro de los MerchantParameters */
    public function getMerchantParam($key)
    {
        $merchantParameters = $this->getMerchantParamArray();
        if(!array_key_exists($key, $merchantParameters)) {
            return false;
        }
        return $merchantParameters[$key];
    }
    /** obtener array con los parámetros recibidos decodificados */
    public function getMerchantParamArray()
    {
        $merchantParameters = $this->getParam('Ds_MerchantParameters');
        $decodedParameters = $this->redsysApi->decodeMerchantParameters($merchantParameters);
        $decodedParametersArray = json_decode($decodedParameters, true);
        
        return $decodedParametersArray;
    }

    /** forma rápida de establecer las opciones a través del array directamente */
    private function setOptions(array $options)
    {
        $this->options = $options;
    }
    /** devolver el array completo de opciones */
    public function getOptionArray()
    {
        return $this->options;
    }
    /** obtener una opción de configuración */
    public function getOption($key)
    {
        if (!array_key_exists($key, $this->options)) {
            return false;
        }
        return $this->options[$key];
    }
    /** 
     * Establecer una opción, posibilidad de reemplazar mediante array
     * 
     * @param string $key  
     * @param string $value
     */
    public function setOption($key, $value = null)
    {
        if (is_array($key)) {
            $this->setOptions = $key;
        } else {
            $this->options[$key] = $value;  
        }
    }
    // comprobar que el array contiene las opciones necesarias
    private function checkOptions()
    {
        $optionPrefix = 'DS_MERCHANT_';
        $required = array('MERCHANTCODE', 'TERMINAL', 'CURRENCY');
        foreach ($required as $option) {
            $optionName = $optionPrefix.$option;
            $value = $this->getOption($optionName);
            if (!$value) {
                throw new Exception("Error, no existe valor para la siguiente opción requerida $optionName", 1);
                
            }
        }
        return true;
    }
    /** 
     * Realizar una comprobación de firma. La firma recibida debe coincidir con la firma
     * generada a partir de los datos recibidos.
     * 
     * @param  string $generada 
     * @param  string $recibida 
     * @return bool
     */
    public function checkSignature($generada = null, $recibida = null)
    {
        if ($generada) {
            $firmaGenerada = $generada;
        } else {
            //  obtener clave
            $sha256key = $this->getOption('sha256key');
            // obtener array de parámetros
            $parameters = $this->getMerchantParamArray();
            // generar signature de la misma forma que cuando el cliente envía
            $redsysApiCheck = new RedsysApi();
            // rellenar nuevo objeto, al igual que en el cliente
            foreach ($parameters as $key => $value) {
                $redsysApiCheck->setParameter($key, $value);
            }
            $firmaGenerada = $redsysApiCheck->createMerchantSignature($sha256key);
        }

        if ($recibida) {
            $firmaRecibida = $recibida;
        } else {
            $firmaRecibida = $this->getParam('Ds_Signature');
        }

        if ($firmaRecibida !== $firmaGenerada) {
            $this->setError('SIS0435');
            return false;
        }
        return true;
    }
    /**
     * Parámetros que deben haber sido recibidos por
     * POST obligatoriamente
     * 
     * @return boolean
     */
    private function checkParameters()
    {
        $postErrorMap = array(
            'Ds_SignatureVersion'   => 'SIS0429',
            'Ds_Signature'          => 'SIS0435',
            'Ds_MerchantParameters' => 'SIS0431'
        );
        foreach ($postErrorMap as $key => $value) {
            // primero comprobar que existen
            if (!$this->getParam($key)) {
                // set error
                $this->setError($postErrorMap[$key]);
                return false;
            }
        }
        return true;
    }
    /** 
     * Comprobar errores en parámetros merchant recibidos.
     * Algunos deben existir, y otro deben existir y
     * coincidir con una opción del TPV
     *
     * @return boolean
     */
    private function _checkMerchantParameters()
    {
        $errorMap = array(
            'Ds_Merchant_Order'          => array(
                'req'   => 'SIS0074',
            ),
            'Ds_Merchant_MerchantCode'   => array(
                'req'   => 'SIS0008',
            ),
            'Ds_Merchant_Terminal'       =>  array(
                'req'   => 'SIS0010',
            ),
            'Ds_Merchant_Amount'         => array(
                'req'   => 'SIS0018'
            ),
            'Ds_Merchant_Currency'       => array(
                'req'   => 'SIS0015',
            ),
        );

        foreach ($errorMap as $name => $values) {
            // comprobar que los parámetros obligatorios existen
            if(array_key_exists('req', $values)) {
                $value = $this->getMerchantParam(strtoupper($name));
                if (!$value) {
                    $this->setError($values['req']);
                    return false;
                }
            }
        }
        return true;
    }
    /**
     * Obtener el objeto respuesta
     * 
     * @return RedsysAPI
     */
    public function getResponse()
    {
        return $this->response;
    }
    /**
     * Generar una respuesta simulando una respuesta de un TPV de redsys
     * @todo: añadir Ds_SignatureVersion a la respuesta
     * @param string $type tipo de respuesta a generar
     */
    public function createFakeResponse($type = 'asincrona')
    {
        $success = true;
        $this->getResponse()->setParameter('Ds_Response', 99);

        // realizar comprobación de firma antes de seguir adelante, ya que la respuesta depende de la firma
        $success = $this->checkSignature();

        // ahora comprobar que se han recibido los parámetros correctos
        $success = $this->checkParameters();

        // comprobar merchant parameters para código de respuesta
        $success = $this->_checkMerchantParameters();

        // obtener objeto de respuesta
        $redsysResponse = $this->getResponse();

        // URL DE VUELTA, dependerá de si queremos ok o ko
        $urlBack = ($success) ? $this->getMerchantParam('DS_MERCHANT_URLOK') : $this->getMerchantParam('DS_MERCHANT_URLKO');
        $merchantUrl = $this->getMerchantParam('DS_MERCHANT_MERCHANTURL');
        // Crer parámetros de la respuesta
        $responseParams = $redsysResponse->createMerchantParameters();
        // Crear firma a enviar en la respuesta
        $responseSignature = $redsysResponse->createMerchantSignatureNotif($this->getOption('sha256key'), $responseParams);        
        // realizar una petición post, y una redirección para simular una respuesta asíncrona
        if ('asincrona' === $type) {
            // crear el array de datos que se enviarán de vuelta
            $postParams = array();
            $postParams['Ds_SignatureVersion'] = 'HMAC_SHA256_V1';
            $postParams['Ds_Signature'] = $responseSignature;
            $postParams['Ds_MerchantParameters'] = $responseParams;
            // hacer petición curl y redireccionar
            $curl = new Curl(array('base' => $this->getMerchantParam('DS_MERCHANT_MERCHANTURL')));
            $curl->post('', array(), $postParams);
            // esperar para dejar terminar la petición post
            sleep(1);
            // redireccionar a la url ko/ok
            die(header('Location: ' . $urlBack));
        }
        // generar un formulario para simular una respuesta síncrona.
        if ('sincrona' === $type) {
            $form = "
                <form action=$merchantUrl method=POST name='frm'>
                    <input type='hidden' name='Ds_Signature' value=" . $responseSignature. ">
                    <input type='hidden' name='Ds_MerchantParameters' value=" . $responseParams . ">
                    <input type='hidden' name='Ds_SignatureVersion' value=" . $postParams['Ds_SignatureVersion'] . ">
                </form>
                <script languaje='javascript'>
                    document.frm.submit();
                </script>
            ";
            echo $form;
        }
    }
    /**
     * Sólo puede haber un código de error.
     * 
     * @param string $code [description]
     * @return
     */
    protected function setError($code)
    {
        if (!$this->error) {
            $response = $this->getResponse();
            $response->setParameter('Ds_Response', $code);
            $this->error = true;
        }
    }
}