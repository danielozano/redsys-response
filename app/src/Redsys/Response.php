<?php
namespace Redsys;

use Exception;
use Redsys\Api\RedsysAPI;
use Redsys\Curl;

class Response
{
	private $dsParameters;

	private $merchantParamsArray;

	private $response;

	protected $redsysApi;
	
	public $options;

	public function __construct(RedsysAPI $redsysApi, array $options)
	{
		$this->redsysApi = $redsysApi;
		$this->setOptions($options);
		$this->setDsParams();
		/* create response object*/
		$this->response = new RedsysAPI();
		// datos del objeto respuesta
		$responseParameters = array(
            'Ds_Amount' => $this->getMerchantParam('DS_MERCHANT_AMOUNT'),
            'Ds_Currency' => $this->getMerchantParam('DS_MERCHANT_CURRENCY'),
            'Ds_Order' => $this->getMerchantParam('DS_MERCHANT_ORDER'),
            'Ds_MerchantCode' => $this->getMerchantParam('DS_MERCHANT_MERCHANTCODE'),
            'Ds_Terminal' => $this->getMerchantParam('DS_MERCHANT_TERMINAL'),
            'Ds_TransactionType' => 0
        );

        foreach ($responseParameters as $key => $value)
        {
        	$this->response->setParameter($key, $value);
        }
	}

	/** inicializar los parámetros recibidos */
	private function setDsParams()
	{
		$dsKeys = array('Ds_SignatureVersion', 'Ds_Signature', 'Ds_MerchantParameters');
		if (empty($_POST))
		{
			throw new Exception("No se han recibido datos", 1);
		}
		foreach ($dsKeys as $key)
		{
			if (!array_key_exists($key, $_POST))
			{
				throw new Exception("No se ha recibido el siguiente parámetro: $key", 1);
			}
			$this->dsParameters[$key] = $_POST[$key];
		}
	}
	/** obtener uno de los tres parámetros recibidos */
	public function getParam($key)
	{
		if (!array_key_exists($key, $this->dsParameters))
		{
			return;
		}
		return $this->dsParameters[$key];
	}
	/** obtener un sólo parámetro de los MerchantParameters */
	public function getMerchantParam($key)
	{
		$merchantParameters = $this->getMerchantParamArray();

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
		if (!array_key_exists($key, $this->options))
		{
			return;
		}
		return $this->options[$key];
	}
	/** establecer una opción, posibilidad de reemplazar mediante array */
	public function setOption($key, $value = null)
	{
		if (is_array($key))
		{
			$this->setOptions = $key;
		}
		else
		{
			$this->options[$key] = $value;	
		}
	}
	/** comprobar que la firma recibida y la generada a partir de datos recibidos son iguales */
	public function checkSignature($generada = null, $recibida = null)
	{
		if ($generada)
		{
			$firmaGenerada = $generada;
		}
		else
		{
			//	obtener clave
			$sha256key = $this->getOption('sha256key');
			// obtener array de parámetros
			$parameters = $this->getMerchantParamArray();
			// generar signature de la misma forma que cuando el cliente envía
			$redsysApiCheck = new RedsysApi();
			// rellenar nuevo objeto, al igual que en el cliente
			foreach ($parameters as $key => $value)
			{
				$redsysApiCheck->setParameter($key, $value);
			}
			$firmaGenerada = $redsysApiCheck->createMerchantSignature($sha256key);
		}

		if ($recibida)
		{
			$firmaRecibida = $recibida;
		}
		else
		{
			$firmaRecibida = $this->getParam('Ds_Signature');
		}

		if ($firmaRecibida !== $firmaGenerada)
		{
			throw new Exception("Las firmas no coinciden", 1);
		}
		return true;
	}
	public function getResponse()
	{
		return $this->response;
	}
	public function createFakeResponse($type = 'asincrona')
	{
		if (!$this->checkSignature())
		{
			throw new Exception("Fallo en la firma", 1);
		}
        // rellenar objeto respuesta
        $redsysResponse = $this->getResponse();
        // URL DE VUELTA
        $urlBack = $this->getMerchantParam('DS_MERCHANT_URLOK');
        // Crear firma
        $responseSignature = $redsysResponse->createMerchantSignature($this->getOption('sha256key'));
        // Crer parámetros
        $responseParams = $redsysResponse->createMerchantParameters();
        // comprobar si queremos repuesta síncrona o asíncrona
        // TODO: implementar códigos de respuesta
        if ('asincrona' === $type)
        {

        }

        if ('sincrona' === $type)
        {
            $form = "
                <form action=$urlBack method=POST name='frm'>
                    <input type='hidden' name='Ds_Signature' value=" . $responseSignature. ">
                    <input type='hidden' name='Ds_MerchantParameters' value=" . $responseParams . ">
                </form>
                <script languaje='javascript'>
                    document.frm.submit();
                </script>
            ";
            echo $form;
        }
	}
}