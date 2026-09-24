<?php

declare(strict_types=1);

namespace Mlevent\Fatura;

use GuzzleHttp\Cookie\CookieJar;
use Mlevent\Fatura\Exceptions\ApiException;
use Mlevent\Fatura\Exceptions\BadResponseException;

class Client
{
    /**
     * @var array response
     */
    protected array $response = [];

    /**
     * @var array headers
     */
    protected static $headers = [
        'accept'       => 'application/json, text/javascript, */*; q=0.01',
        'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
    ];

    /**
     * @var CookieJar|null
     */
    protected static ?CookieJar $cookieJar = null;

    /**
     * @var string|null
     */
    protected static ?string $origin = null;

    /**
     * @var string|null
     */
    protected static ?string $referer = null;

    public static function resetSession(): void
    {
        self::$cookieJar = new CookieJar();
        self::$origin = null;
        self::$referer = null;
    }

    public static function setOrigin(?string $origin = null): void
    {
        self::$origin = $origin;
    }

    public static function setReferer(?string $referer = null): void
    {
        self::$referer = $referer;
    }

    protected static function getCookieJar(): CookieJar
    {
        return self::$cookieJar ??= new CookieJar();
    }

    protected static function getHeaders(array $headers = []): array
    {
        return array_filter(array_merge(self::$headers, [
            'origin'  => self::$origin,
            'referer' => self::$referer,
        ], $headers));
    }

    /**
     * request
     *
     * @param string     $url
     * @param array|null $parameters
     * @param boolean    $post
     */
    public function __construct(string $url, ?array $parameters = null, bool $post = true, array $options = [])
    {
        try {
            $headers = $options['headers'] ?? [];
            unset($options['headers']);
            $expectJson = $options['expect_json'] ?? true;
            unset($options['expect_json']);

            $requestOptions = array_merge([
                'cookies' => self::getCookieJar(),
                'headers' => self::getHeaders($headers),
            ], $options);

            if ($post) {
                $requestOptions['form_params'] = $parameters ?? [];
            } elseif (!empty($parameters)) {
                $requestOptions['query'] = $parameters;
            }

            $request = (new \GuzzleHttp\Client())->request($post ? 'POST' : 'GET', $url, $requestOptions);
            $body = $request->getBody()->getContents();
            if ($expectJson) {
                if ($response = json_decode($body, true)) {
                    if (is_array($response)) {
                        $this->response = $response;
                    }
                }
                //if (!$this->response || (isset($this->response['data']) && !is_array($this->response['data'])) || isset($this->response['error']) || !empty($this->response['data']['hata'])) {
                if (!$this->response || isset($this->response['error']) || !empty($this->response['data']['hata'])) {
                    throw new ApiException('İstek başarısız oldu.', $parameters, $this->response, $request->getStatusCode());
                }
                // Liste uçları hatayı data[0].error içinde döner (ör. "Seçilen tarih aralığı 7 günden fazla olamaz!")
                if (!empty($this->response['data'][0]['error'])) {
                    throw new ApiException((string) $this->response['data'][0]['error'], $parameters, $this->response, $request->getStatusCode());
                }
            }
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new BadResponseException($e->getMessage(), $parameters, null, $e->getCode());
        }
    }

    /**
     * get
     *
     * @param  string|null $element
     * @return mixed
     */
    public function get(?string $element = null): mixed
    {
        return is_null($element)
            ? $this->response
            : $this->response[$element];
    }

    /**
     * object
     *
     * @param  string|null $element
     * @return mixed
     */
    public function object(?string $element = null): mixed
    {
        $response = json_decode(json_encode($this->response, JSON_FORCE_OBJECT), false);

        return is_null($element)
            ? $response
            : $response->$element;
    }
}
