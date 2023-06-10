<?php

namespace App\exception;


class CustomException extends \Exception
{
    /**
     * @var int
     */
    private $httpStatusCode;

    /**
     * @var string
     */
    private $errorType;

    /**
     * @var null|string
     */
    private $errorDetail;

    /**
     * @var null|string
     */
    private $helpUri;

    /**
     * @var string
     */
    private $backTrace;

    /**
     * Throw a new exception.
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param string $errorType Error type
     * @param int $httpStatusCode HTTP status code to send (default = 400)
     * @param null|string $errorDetail Error detail for logging
     * @param null|string $backTrace
     */
    public function __construct($message, $code, $errorType, $httpStatusCode = 400, $errorDetail = null, $backTrace = null)
    {
        parent::__construct($message, $code);

        $backTrace = $this->editBackTrace($backTrace);

        $this->httpStatusCode = $httpStatusCode;
        $this->errorType = $errorType;
        $this->errorDetail = $errorDetail;
        $this->backTrace = ' file:' . $backTrace['file'] .
            ' line:' . $backTrace['line'] .
            ' class:' . $backTrace['class'] .
            ' function:' . $backTrace['function'];

    }

    public function editBackTrace($backTraceArray)
    {
        $result = array();
        $result['file'] = $backTraceArray['file'] ?? 'null';
        $result['line'] = $backTraceArray['line'] ?? 'null';
        $result['class'] = $backTraceArray['class'] ?? 'null';
        $result['function'] = $backTraceArray['function'] ?? 'null';

        return $result;
    }

    /**
     * Returns the HTTP status code to send when the exceptions is output.
     *
     * @return int
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /**
     * @return null|string
     */
    public function getErrorDetail()
    {
        return $this->errorDetail;
    }

    /**
     * @return string
     */
    public function getErrorType()
    {
        return $this->errorType;
    }

    /**
     * @return string
     */
    public function getBackTrace()
    {
        return $this->backTrace;
    }

    public static function notFound($code, $errorDetail = null)
    {
        return new static(
            "404 Not Found",
            $code,
            'not_found',
            404,
            $errorDetail,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]
        );
    }

    /**
     * @param $code
     * @param $message
     * @param null $errorDetail
     * @return static
     */
    public static function clientError($code, $message, $errorDetail = null)
    {
        return new static(
            $message,
            $code,
            'client_error',
            $code,
            $errorDetail,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]
        );
    }


    /**
     * @param $code
     * @param $errorDetail
     * @param null $message
     * @return static
     *
     * @codeCoverageIgnore
     */
    public static function serverError($code, $errorDetail, $message = null)
    {
        if ($message == null) {
            $message = 'Something went wrong, please try again!' . json_encode($errorDetail);
        }
        return new static(
            $message,
            $code,
            'server_error',
            500,
            $errorDetail,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]
        );
    }

    /**
     * @param $code
     * @param $errorDetail
     * @param null $message
     * @return static
     *
     * @codeCoverageIgnore
     */
    public static function dbError($code, $errorDetail, $message = null)
    {
        if ($message == null) {
            $message = 'Something went wrong, please try again! ' . json_encode($errorDetail);
        }

        $errorDetailArr = json_decode($errorDetail);
        if ($errorDetailArr[1] == 1062) {
            $message = 'Duplicate entry!';
        }

        return new static(
            $message,
            $code,
            'db_error',
            500,
            $errorDetail,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]
        );
    }

    /**
     * Generate a HTTP response.
     *
     * @param ResponseInterface $response
     * @param bool $useFragment True if errors should be in the URI fragment instead of query string
     *
     * @return ResponseInterface
     */
    public function generateHttpResponse(ResponseInterface $response, $useFragment = false)
    {
        $headers = $this->getHttpHeaders();

        $payload = [
            'error' => $this->getErrorType(),
            'message' => $this->getMessage()
        ];

        if ($this->errorDetail !== null) {
            $payload['errorDetail'] = $this->errorDetail . json_encode($this->getBackTrace());
        }

        if ($this->helpUri !== null) {
            if ($useFragment === true) {
                $this->helpUri .= (strstr($this->helpUri, '#') === false) ? '#' : '&';
            } else {
                $this->helpUri .= (strstr($this->helpUri, '?') === false) ? '?' : '&';
            }

            return $response->withStatus(302)->withHeader('Location', $this->helpUri . http_build_query($payload));
        }

        foreach ($headers as $header => $content) {
            $response = $response->withHeader($header, $content);
        }

        $response->getBody()->write(json_encode($payload));

        return $response->withStatus($this->getHttpStatusCode());
    }

    /**
     * Get all headers that have to be send with the error response.
     *
     * @return array Array with header values
     */
    public function getHttpHeaders()
    {
        $headers = [
            'Content-type' => 'application/json',
        ];

        if ($this->errorType === 'invalid_client') {
            $authScheme = 'Basic';
            if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER) !== false
                && strpos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer') === 0
            ) {
                $authScheme = 'Bearer';
            }
            $headers['WWW-Authenticate'] = $authScheme . ' realm="OAuth"';
        }

        return $headers;
    }
}