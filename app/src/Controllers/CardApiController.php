<?php

namespace App\Controllers;

use App\Models\Card;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;
use SilverStripe\ORM\DataList;

class CardApiController extends RequestHandler
{
    private static array $url_handlers = [
        '' => 'index',
    ];

    private static array $allowed_actions = [
        'index',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() === 'OPTIONS') {
            return $this->jsonResponse([]);
        }

        if ($request->httpMethod() !== 'GET') {
            return $this->jsonError(405, 'Method not allowed');
        }

        try {
            $search = trim((string) $request->getVar('search'));
            $adult = $request->getVar('adult');
            // "dare" (default), "truth" or "all"
            $type = strtolower(trim((string) $request->getVar('type')));
            $page = max(1, (int) $request->getVar('page'));
            $limit = min(100, max(10, (int) ($request->getVar('limit') ?: 30)));

            /** @var DataList $cards */
            $cards = Card::get()->filter('Official', true);

            if ($adult === 'false' || $adult === '0') {
                $cards = $cards->filter('AdultsOnly', false);
            } elseif ($adult === 'true' || $adult === '1') {
                $cards = $cards->filter('AdultsOnly', true);
            }

            if ($type === 'truth') {
                $cards = $cards->filter('Type', Card::TYPE_TRUTH);
            } elseif ($type !== 'all') {
                $cards = $cards->filter('Type', Card::TYPE_DARE);
            }

            if ($search !== '') {
                $cards = $cards->filter('Dare:PartialMatch', $search);
            }

            $total = $cards->count();
            $offset = ($page - 1) * $limit;

            $items = [];
            foreach ($cards->limit($limit, $offset) as $card) {
                $items[] = [
                    'id' => $card->ID,
                    'dare' => $card->Dare,
                    'type' => $card->Type ?: Card::TYPE_DARE,
                    'level' => (int) $card->Level,
                    'adultsOnly' => (bool) $card->AdultsOnly,
                ];
            }

            return $this->jsonResponse([
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'cards' => $items,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError(500, $e->getMessage());
        }
    }

    private function jsonResponse(array $data, int $status = 200): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->setStatusCode($status);
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($data));
        return $response;
    }

    private function jsonError(int $status, string $message): HTTPResponse
    {
        return $this->jsonResponse(['error' => $message], $status);
    }
}
