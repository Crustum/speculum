<?php
declare(strict_types=1);

namespace Crustum\Speculum\Mcp\Tools;

use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\Server\Tool;
use Crustum\Mcp\Server\Tools\Annotations\IsIdempotent;
use Crustum\Speculum\Speculum;
use Override;
use Throwable;

/**
 * Control Speculum: monitored tags (UI Monitoring) and recording pause/resume.
 */
#[IsIdempotent]
class Control extends Tool
{
    /**
     * MCP tool name.
     *
     * @var string
     */
    protected string $name = 'speculum_control';

    /**
     * MCP tool description.
     *
     * @var string
     */
    protected string $description = 'Control Speculum. Monitoring (like the UI): monitor/unmonitor tags; status lists monitored_tags. Recording: pause/resume (dashboard pause button). Tags only affect recording when the host uses a Speculum::filter with hasMonitoredTag().';

    /**
     * @inheritDoc
     */
    #[Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description(
                    'status: state + monitored_tags (default). '
                    . 'monitor / unmonitor: add or remove a tag (requires tag). '
                    . 'pause / resume: stop or start recording new entries.',
                )
                ->enum(['status', 'monitor', 'unmonitor', 'pause', 'resume']),
            'tag' => $schema->string()
                ->description('Tag for monitor / unmonitor (same as Monitoring UI).'),
        ];
    }

    /**
     * Handle the tool request.
     *
     * @param \Crustum\Mcp\Request $request MCP request.
     * @return \Crustum\Mcp\Response Control result.
     */
    public function handle(Request $request): Response
    {
        $action = $request->get('action');
        $actionName = is_string($action) && $action !== '' ? $action : 'status';

        return match ($actionName) {
            'status' => Response::json([
                'action' => 'status',
                'status' => Speculum::recordingStatus(),
            ]),
            'monitor' => $this->monitorTag($request),
            'unmonitor' => $this->unmonitorTag($request),
            'pause' => $this->pause(),
            'resume' => $this->resume(),
            default => Response::error(
                'Unknown action. Use status, monitor, unmonitor, pause, or resume.',
            ),
        };
    }

    /**
     * Start monitoring a tag.
     *
     * @param \Crustum\Mcp\Request $request MCP request.
     * @return \Crustum\Mcp\Response
     */
    protected function monitorTag(Request $request): Response
    {
        $tag = $this->requireTag($request);
        if ($tag instanceof Response) {
            return $tag;
        }

        try {
            Speculum::getRepository()->monitor([$tag]);
            Speculum::getRepository()->loadMonitoredTags();
        } catch (Throwable $throwable) {
            return Response::error($throwable->getMessage());
        }

        return Response::json([
            'action' => 'monitor',
            'tag' => $tag,
            'status' => Speculum::recordingStatus(),
        ]);
    }

    /**
     * Stop monitoring a tag.
     *
     * @param \Crustum\Mcp\Request $request MCP request.
     * @return \Crustum\Mcp\Response
     */
    protected function unmonitorTag(Request $request): Response
    {
        $tag = $this->requireTag($request);
        if ($tag instanceof Response) {
            return $tag;
        }

        try {
            Speculum::getRepository()->stopMonitoring([$tag]);
            Speculum::getRepository()->loadMonitoredTags();
        } catch (Throwable $throwable) {
            return Response::error($throwable->getMessage());
        }

        return Response::json([
            'action' => 'unmonitor',
            'tag' => $tag,
            'status' => Speculum::recordingStatus(),
        ]);
    }

    /**
     * Require a non-empty tag argument.
     *
     * @param \Crustum\Mcp\Request $request MCP request.
     * @return \Crustum\Mcp\Response|string
     */
    protected function requireTag(Request $request): string|Response
    {
        $tag = $request->get('tag');
        if (!is_string($tag) || trim($tag) === '') {
            return Response::error('The "tag" argument is required for monitor / unmonitor.');
        }

        return trim($tag);
    }

    /**
     * Pause recording and return status.
     *
     * @return \Crustum\Mcp\Response
     */
    protected function pause(): Response
    {
        Speculum::pauseRecording();

        return Response::json([
            'action' => 'pause',
            'status' => Speculum::recordingStatus(),
        ]);
    }

    /**
     * Resume recording and return status.
     *
     * @return \Crustum\Mcp\Response
     */
    protected function resume(): Response
    {
        Speculum::resumeRecording();

        return Response::json([
            'action' => 'resume',
            'status' => Speculum::recordingStatus(),
        ]);
    }
}
