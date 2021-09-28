<?php

namespace MailerSend\Tests\Endpoints;

use Http\Mock\Client;
use MailerSend\Common\Constants;
use MailerSend\Common\HttpLayer;
use MailerSend\Endpoints\SpamComplaint;
use MailerSend\Exceptions\MailerSendAssertException;
use MailerSend\Helpers\Builder\SuppressionParams;
use MailerSend\Tests\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tightenco\Collect\Support\Arr;

class SpamComplaintTest extends TestCase
{
    protected SpamComplaint $spamComplaint;
    protected ResponseInterface $defaultResponse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new Client();

        $this->spamComplaint = new SpamComplaint(new HttpLayer(self::OPTIONS, $this->client), self::OPTIONS);
        $this->defaultResponse = $this->createMock(ResponseInterface::class);
        $this->defaultResponse->method('getStatusCode')->willReturn(200);
    }

    /**
     * @dataProvider validGetAllDataProvider
     * @throws MailerSendAssertException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function test_get_all(array $params): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->client->addResponse($response);

        $response = $this->spamComplaint->getAll(
            Arr::get($params, 'domain_id'),
            Arr::get($params, 'limit'),
        );

        $request = $this->client->getLastRequest();
        parse_str($request->getUri()->getQuery(), $query);

        self::assertEquals('GET', $request->getMethod());
        self::assertEquals('/v1/suppressions/spam-complaints', $request->getUri()->getPath());
        self::assertEquals(200, $response['status_code']);
        self::assertEquals(Arr::get($params, 'domain_id'), Arr::get($query, 'domain_id'));
        self::assertEquals(Arr::get($params, 'limit'), Arr::get($query, 'limit'));
    }

    /**
     * @dataProvider invalidGetAllDataProvider
     * @throws MailerSendAssertException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function test_get_all_with_errors(array $params, string $errorMessage): void
    {
        $this->expectException(MailerSendAssertException::class);
        $this->expectExceptionMessage($errorMessage);

        $this->spamComplaint->getAll(
            Arr::get($params, 'domain_id'),
            Arr::get($params, 'limit'),
        );
    }

    /**
     * @throws MailerSendAssertException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function test_create(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->client->addResponse($response);

        $params = (new SuppressionParams())
            ->setDomainId('domain_id')
            ->setRecipients(['recipient']);

        $response = $this->spamComplaint->create($params);

        $request = $this->client->getLastRequest();
        $request_body = json_decode((string)$request->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals('POST', $request->getMethod());
        self::assertEquals('/v1/suppressions/spam-complaints', $request->getUri()->getPath());
        self::assertEquals(200, $response['status_code']);
        self::assertSame('domain_id', Arr::get($request_body, 'domain_id'));
        self::assertSame(['recipient'], Arr::get($request_body, 'recipients'));
    }

    /**
     * @throws MailerSendAssertException
     * @throws \JsonException
     * @throws \Psr\Http\Client\ClientExceptionInterface
     */
    public function test_create_requires_domain_id(): void
    {
        $this->expectException(MailerSendAssertException::class);
        $this->expectExceptionMessage('Domain id is required.');

        $params = (new SuppressionParams())
            ->setRecipients(['recipient']);

        $this->spamComplaint->create($params);
    }

    public function test_create_requires_recipients(): void
    {
        $this->expectException(MailerSendAssertException::class);
        $this->expectExceptionMessage('Recipients is required.');

        $params = (new SuppressionParams())
            ->setDomainId('domain_id');

        $this->spamComplaint->create($params);
    }

    /**
     * @dataProvider validDeleteDataProvider
     * @throws \Psr\Http\Client\ClientExceptionInterface
     * @throws \JsonException
     * @throws MailerSendAssertException
     */
    public function test_delete(array $params): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $this->client->addResponse($response);

        $response = $this->spamComplaint->delete(
            Arr::get($params, 'ids'),
            Arr::get($params, 'all', false),
        );

        $request = $this->client->getLastRequest();
        $request_body = json_decode((string)$request->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertEquals('DELETE', $request->getMethod());
        self::assertEquals('/v1/suppressions/spam-complaints', $request->getUri()->getPath());
        self::assertEquals(200, $response['status_code']);
        self::assertSame(Arr::get($params, 'ids'), Arr::get($request_body, 'ids'));
        self::assertSame(Arr::get($params, 'all', false), Arr::get($request_body, 'all'));

    }

    public function test_delete_requires_either_ids_or_all(): void
    {
        $this->expectException(MailerSendAssertException::class);
        $this->expectExceptionMessage('Either ids or all must be provided.');

        $this->spamComplaint->delete();
    }

    public function validGetAllDataProvider(): array
    {
        return [
            'without limit' => [
                'params' => [
                    'domain_id' => 'domain_id',
                ],
            ],
            'with limit' => [
                'params' => [
                    'domain_id' => 'domain_id',
                    'limit' => 10,
                ],
            ]
        ];
    }

    public function invalidGetAllDataProvider(): array
    {
        return [
            'empty domain_id' => [
                'params' => [
                    'domain_id' => '',
                ],
                'errorMessage' => 'Domain id is required.',
            ],
            'with limit under 10' => [
                'params' => [
                    'domain_id' => 'domain_id',
                    'limit' => 9,
                ],
                'errorMessage' => 'Limit is supposed to be between ' . Constants::MIN_LIMIT . ' and ' . Constants::MAX_LIMIT .  '.',
            ],
            'with limit over 100' => [
                'params' => [
                    'domain_id' => 'domain_id',
                    'limit' => 101,
                ],
                'errorMessage' => 'Limit is supposed to be between ' . Constants::MIN_LIMIT . ' and ' . Constants::MAX_LIMIT .  '.',
            ],
        ];
    }

    public function validDeleteDataProvider(): array
    {
        return [
            'with ids' => [
                'params' => [
                    'ids' => ['id']
                ],
            ],
            'all' => [
                'params' => [
                    'all' => true,
                ],
            ],
        ];
    }
}
