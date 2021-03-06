<?php
/**********************************************************\
|                                                          |
|                          hprose                          |
|                                                          |
| Official WebSite: http://www.hprose.com/                 |
|                   http://www.hprose.org/                 |
|                                                          |
\**********************************************************/

/**********************************************************\
 *                                                        *
 * Hprose/Yii/Service.php                                 *
 *                                                        *
 * hprose yii http service class for php 5.3+             *
 *                                                        *
 * LastModified: Jun 28, 2015                             *
 * Author: Ma Bingyao <andot@hprose.com>                  *
 *                                                        *
\**********************************************************/

namespace Hprose\Yii {
    class Service extends \Hprose\Base\Service {
        private $crossDomain = false;
        private $P3P = false;
        private $get = true;
        private $origins = array();
        public $onSendHeader = null;

        private function sendHeader($context) {
            if ($this->onSendHeader !== null) {
                $sendHeader = $this->onSendHeader;
                call_user_func($sendHeader, $context);
            }
            $context->response->headers->set('Content-Type', 'text/plain');
            if ($this->P3P) {
                $context->response->headers->set('P3P',
                        'CP="CAO DSP COR CUR ADM DEV TAI PSA PSD ' .
                        'IVAi IVDi CONi TELo OTPi OUR DELi SAMi OTRi ' .
                        'UNRi PUBi IND PHY ONL UNI PUR FIN COM NAV ' .
                        'INT DEM CNT STA POL HEA PRE GOV"');
            }
            if ($this->crossDomain) {
                if ($context->request->headers->has('Origin') &&
                    $context->request->headers->get('Origin') != "null") {
                    $origin = $context->request->headers->get('Origin');
                    if (count($this->origins) === 0 ||
                        isset($this->origins[strtolower($origin)])) {
                        $context->response->headers->set('Access-Control-Allow-Origin', $origin);
                        $context->response->headers->set('Access-Control-Allow-Credentials', 'true');
                    }
                }
                else {
                    $context->response->headers->set('Access-Control-Allow-Origin', '*');
                }
            }
        }
        public function isCrossDomainEnabled() {
            return $this->crossDomain;
        }
        public function setCrossDomainEnabled($enable = true) {
            $this->crossDomain = $enable;
        }
        public function isP3PEnabled() {
            return $this->P3P;
        }
        public function setP3PEnabled($enable = true) {
            $this->P3P = $enable;
        }
        public function isGetEnabled() {
            return $this->get;
        }
        public function setGetEnabled($enable = true) {
            $this->get = $enable;
        }
        public function addAccessControlAllowOrigin($origin) {
            $count = count($origin);
            if (($count > 0) && ($origin[$count - 1] === "/")) {
                $origin = substr($origin, 0, -1);
            }
            $this->origins[strtolower($origin)] = true;
        }
        public function removeAccessControlAllowOrigin($origin) {
            $count = count($origin);
            if (($count > 0) && ($origin[$count - 1] === "/")) {
                $origin = substr($origin, 0, -1);
            }
            unset($this->origins[strtolower($origin)]);
        }
        public function handle($app) {
            $request = $app->request;
            $response = $app->response;
            $context = new \stdClass();
            $context->server = $this;
            $context->app = $app;
            $context->request = $request;
            $context->response = $response;
            $context->session = $app->session;
            $context->userdata = new \stdClass();

            $response->format = self::FORMAT_RAW;

            $self = $this;
            $this->user_fatal_error_handler = function($error) use ($self, $context) {
                $context->response->data = $self->sendError($error, $context);
            };

            $this->sendHeader($context);
            $result = '';

            if (($request->isGet) && $this->get) {
                $result = $this->doFunctionList($context);
            }
            elseif ($request->isPost) {
                $data = $request->rawBody;
                $result = $this->defaultHandle($data, $context);
            }
            if ($result instanceof \Hprose\Future) {
                $result->then(function($result) use ($response) { $response->data = $result; });
            }
            else {
                $response->data = $result;
            }
            return $response;
        }
    }
}
