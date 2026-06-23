<?php

namespace App\Controllers;

use App\Models\Card;
use App\Models\Game;
use App\Models\Player;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;

class GameApiController extends RequestHandler
{
    private static array $url_handlers = [
        '$Hash/$Action' => 'index',
        '$Hash'         => 'index',
        ''              => 'index',
    ];

    private static array $allowed_actions = [
        'index',
    ];

    public function index(HTTPRequest $request): HTTPResponse
    {
        if ($request->httpMethod() === 'OPTIONS') {
            return $this->jsonResponse([]);
        }

        $hash = $request->param('Hash');
        $action = $request->param('Action');
        $method = $request->httpMethod();

        try {
            if (!$hash && $method === 'POST') {
                return $this->createGame();
            }

            if ($hash) {
                /** @var Game|null $game */
                $game = Game::get()->find('Hash', $hash);
                if (!$game) {
                    return $this->jsonError(404, 'Game not found');
                }

                if (!$action) {
                    return $method === 'GET'
                        ? $this->getGame($game)
                        : $this->jsonError(405, 'Method not allowed');
                }

                switch ($action) {
                    case 'setup':
                        return $this->setupGame($request, $game);
                    case 'cards':
                        return $this->setCards($request, $game);
                    case 'card':
                        return $this->addCustomCard($request, $game);
                    case 'draw':
                        return $this->drawCard($request, $game);
                    case 'action':
                        return $this->playerAction($request, $game);
                    case 'reset':
                        return $this->resetDeck($game);
                    default:
                        return $this->jsonError(404, 'Unknown action');
                }
            }

            return $this->jsonError(400, 'Bad request');
        } catch (\Throwable $e) {
            return $this->jsonError(500, $e->getMessage());
        }
    }

    private function createGame(): HTTPResponse
    {
        $game = Game::create();
        $game->write();
        return $this->jsonResponse(['hash' => $game->Hash, 'id' => $game->ID], 201);
    }

    private function getGame(Game $game): HTTPResponse
    {
        return $this->jsonResponse($this->serializeGame($game));
    }

    private function setupGame(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);

        if (isset($body['adultType'])) {
            $game->AdultType = $body['adultType'];
        }
        if (isset($body['gameType'])) {
            $game->GameType = $body['gameType'];
        }
        $game->write();

        if (isset($body['players']) && is_array($body['players'])) {
            $game->Players()->removeAll();
            foreach ($body['players'] as $playerName) {
                $name = trim((string) $playerName);
                if ($name === '') {
                    continue;
                }
                $player = Player::create();
                $player->Name = $name;
                $player->GameID = $game->ID;
                $player->write();
            }
        }

        return $this->jsonResponse($this->serializeGame($game));
    }

    private function setCards(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);

        $game->Cards()->removeAll();

        if (!empty($body['cardIds']) && is_array($body['cardIds'])) {
            foreach ($body['cardIds'] as $cardId) {
                $card = Card::get()->byID((int) $cardId);
                if ($card) {
                    $game->Cards()->add($card);
                }
            }
        }

        if (!empty($body['customCards']) && is_array($body['customCards'])) {
            foreach ($body['customCards'] as $dare) {
                $dare = trim((string) $dare);
                if ($dare === '') {
                    continue;
                }
                $card = Card::create();
                $card->Dare = $dare;
                $card->Level = 1;
                $card->Official = false;
                $card->AdultsOnly = false;
                $card->write();
                $game->Cards()->add($card);
            }
        }

        return $this->jsonResponse($this->serializeGame($game));
    }

    private function addCustomCard(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);
        $dare = trim((string) ($body['dare'] ?? ''));

        if ($dare === '') {
            return $this->jsonError(400, 'Dare text is required');
        }

        $card = Card::create();
        $card->Dare = $dare;
        $card->Level = 1;
        $card->Official = false;
        $card->AdultsOnly = false;
        $card->write();

        $game->Cards()->add($card);

        return $this->jsonResponse($this->serializeCard($card), 201);
    }

    private function drawCard(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);
        $cardId = (int) ($body['cardId'] ?? 0);

        if (!$cardId) {
            return $this->jsonError(400, 'cardId is required');
        }

        $used = $this->getUsedCardIds($game);
        if (!in_array($cardId, $used, true)) {
            $used[] = $cardId;
            $game->UsedCardIDs = json_encode($used);
            $game->write();
        }

        return $this->jsonResponse($this->serializeGame($game));
    }

    private function playerAction(HTTPRequest $request, Game $game): HTTPResponse
    {
        $body = $this->parseBody($request);
        $cardId = (int) ($body['cardId'] ?? 0);
        $type = (string) ($body['type'] ?? '');

        if (!$cardId) {
            return $this->jsonError(400, 'cardId is required');
        }

        if ($type === 'return') {
            $used = array_values(array_filter(
                $this->getUsedCardIds($game),
                fn($id) => $id !== $cardId
            ));
            $game->UsedCardIDs = json_encode($used);
            $game->write();
            return $this->jsonResponse($this->serializeGame($game));
        }

        $playerId = (int) ($body['playerId'] ?? 0);
        if (!$playerId) {
            return $this->jsonError(400, 'playerId is required');
        }

        /** @var Player|null $player */
        $player = $game->Players()->byID($playerId);
        if (!$player) {
            return $this->jsonError(404, 'Player not found');
        }

        $card = Card::get()->byID($cardId);
        if (!$card) {
            return $this->jsonError(404, 'Card not found');
        }

        if ($type === 'complete') {
            $player->CompletionCount++;
            $player->CardsCompleted()->add($card);
        } elseif ($type === 'fail') {
            $player->FailureCount++;
            $player->CardsFailed()->add($card);
        } else {
            return $this->jsonError(400, 'Invalid action type');
        }

        $player->write();

        $used = $this->getUsedCardIds($game);
        if (!in_array($cardId, $used, true)) {
            $used[] = $cardId;
            $game->UsedCardIDs = json_encode($used);
            $game->write();
        }

        return $this->jsonResponse($this->serializeGame($game));
    }

    private function resetDeck(Game $game): HTTPResponse
    {
        $game->UsedCardIDs = json_encode([]);
        $game->write();
        return $this->jsonResponse($this->serializeGame($game));
    }

    private function serializeGame(Game $game): array
    {
        $players = [];
        foreach ($game->Players() as $player) {
            $players[] = [
                'id' => $player->ID,
                'name' => $player->Name,
                'completionCount' => (int) $player->CompletionCount,
                'failureCount' => (int) $player->FailureCount,
            ];
        }

        $cards = [];
        foreach ($game->Cards() as $card) {
            $cards[] = $this->serializeCard($card);
        }

        return [
            'id' => $game->ID,
            'hash' => $game->Hash,
            'adultType' => $game->AdultType,
            'gameType' => $game->GameType,
            'players' => $players,
            'cards' => $cards,
            'usedCardIds' => $this->getUsedCardIds($game),
        ];
    }

    private function serializeCard(Card $card): array
    {
        return [
            'id' => $card->ID,
            'dare' => $card->Dare,
            'level' => (int) $card->Level,
            'adultsOnly' => (bool) $card->AdultsOnly,
            'official' => (bool) $card->Official,
        ];
    }

    private function getUsedCardIds(Game $game): array
    {
        if (empty($game->UsedCardIDs)) {
            return [];
        }
        $decoded = json_decode($game->UsedCardIDs, true);
        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    private function parseBody(HTTPRequest $request): array
    {
        $body = json_decode($request->getBody(), true);
        return is_array($body) ? $body : [];
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
