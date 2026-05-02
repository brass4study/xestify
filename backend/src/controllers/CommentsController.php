<?php

declare(strict_types=1);

namespace Xestify\controllers;

use PDO;
use Xestify\core\Request;
use Xestify\core\Response;

/**
 * CommentsController — REST endpoints for the comments plugin.
 *
 * Data is stored in the generic `plugin_extension_data` table
 * (shared by all extension-type plugins), keyed by plugin_slug = 'comments'.
 *
 * Routes:
 *   GET  /api/v1/plugins/comments/{entity}/{id}  → list comments for a record
 *   POST /api/v1/plugins/comments/{entity}/{id}  → add a comment to a record
 */
class CommentsController
{
    private const PLUGIN_SLUG      = 'comments';
    private const MSG_ENTITY_REQUIRED = 'Entity slug is required.';
    private const MSG_RECORD_REQUIRED = 'Record id is required.';
    private const MSG_BODY_REQUIRED   = 'Comment body is required.';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * GET /api/v1/plugins/comments/{entity}/{id}
     * Returns all comments for the given record, ordered by creation date.
     */
    public function index(array $params, ?Request $request = null): void
    {
        $entity   = (string) ($params['entity'] ?? '');
        $recordId = (string) ($params['id'] ?? '');

        if ($entity === '') {
            Response::make()->notFound(self::MSG_ENTITY_REQUIRED);
            return;
        }

        if ($recordId === '') {
            Response::make()->notFound(self::MSG_RECORD_REQUIRED);
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, entity_slug, record_id,
                    content->>\'body\'      AS body,
                    content->>\'author_id\' AS author_id,
                    created_at
               FROM plugin_extension_data
              WHERE plugin_slug = :plugin
                AND entity_slug = :entity
                AND record_id   = :record_id
              ORDER BY created_at ASC'
        );
        $stmt->execute([
            ':plugin'    => self::PLUGIN_SLUG,
            ':entity'    => $entity,
            ':record_id' => $recordId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::make()->json($rows ?: [], ['total' => count($rows ?: [])]);
    }

    /**
     * POST /api/v1/plugins/comments/{entity}/{id}
     * Adds a new comment to the given record.
     * Body: { "body": "..." }
     */
    public function create(array $params, ?Request $request = null): void
    {
        $request  ??= Request::fromGlobals($params);
        $entity   = (string) ($params['entity'] ?? '');
        $recordId = (string) ($params['id'] ?? '');
        $body     = trim((string) ($request->allBody()['body'] ?? ''));

        if ($entity === '') {
            Response::make()->notFound(self::MSG_ENTITY_REQUIRED);
            return;
        }

        if ($recordId === '') {
            Response::make()->notFound(self::MSG_RECORD_REQUIRED);
            return;
        }

        if ($body === '') {
            Response::make()->unprocessable(self::MSG_BODY_REQUIRED, ['body' => self::MSG_BODY_REQUIRED]);
            return;
        }

        $authorId = $this->resolveAuthorId($request);
        $content  = json_encode(['body' => $body, 'author_id' => $authorId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
             VALUES (:plugin, :entity, :record_id, :content)
             RETURNING id, entity_slug, record_id,
                       content->>\'body\'      AS body,
                       content->>\'author_id\' AS author_id,
                       created_at'
        );
        $stmt->execute([
            ':plugin'    => self::PLUGIN_SLUG,
            ':entity'    => $entity,
            ':record_id' => $recordId,
            ':content'   => $content !== false ? $content : '{}',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::make()->status(201)->json($row ?: []);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveAuthorId(Request $request): ?string
    {
        $user = $request->user();
        if (!is_array($user)) {
            return null;
        }
        $sub = $user['sub'] ?? null;
        return is_string($sub) ? $sub : null;
    }
}
