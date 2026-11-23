<?php

namespace App\Admin\BulkAction;

use App\Models\Card;
use App\Services\DareSimilarity;
use Colymba\BulkManager\BulkAction\Handler;
use Colymba\BulkTools\HTTPBulkToolsResponse;
use Exception;
use SilverStripe\Control\HTTPRequest;

/**
 * Bulk action: mark the selected cards as official.
 * Subclassed by {@link SetUnofficialHandler} for the opposite direction.
 */
class SetOfficialHandler extends Handler
{
    private static $url_segment = 'make-official';

    private static $allowed_actions = ['apply'];

    private static $url_handlers = ['' => 'apply'];

    protected $label = 'Make official';

    protected $buttonClasses = 'font-icon-check-mark-circle';

    protected $xhr = true;

    protected $destructive = false;

    /** Value written to Card::$Official */
    protected bool $official = true;

    /**
     * The base class uses one shared translation key for all handlers,
     * which would give every custom action the same label.
     */
    public function getI18nLabel(): string
    {
        return $this->getLabel();
    }

    public function apply(HTTPRequest $request): HTTPBulkToolsResponse
    {
        $response = HTTPBulkToolsResponse::create(true, $this->gridField);

        try {
            /** @var Card $record */
            foreach ($this->getRecords() ?: [] as $record) {
                if (!$record->canEdit()) {
                    $response->addFailedRecord($record, 'Permission denied');
                    continue;
                }
                $record->Official = $this->official;
                $record->write();
                $response->addSuccessRecord($record);
            }

            DareSimilarity::reset();

            $response->setMessage(sprintf(
                '%d cards marked as %s.',
                count($response->getSuccessRecords() ?? []),
                $this->official ? 'official' : 'unofficial'
            ));
        } catch (Exception $ex) {
            $response->setStatusCode(500);
            $response->setMessage($ex->getMessage());
        }

        return $response;
    }
}
