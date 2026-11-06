<?php

declare(strict_types=1);

namespace App\Yack\Account\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;

/**
 * POST /api/v2/shop/yack/activations/{token} — follows the link of the activation e-mail
 * ({FRONT_URL}/activation/{token}): enables the account (204), 404 for an unknown / used token.
 */
#[ApiResource(
    shortName: 'YackActivation',
    operations: [
        new Post(
            uriTemplate: '/shop/yack/activations/{token}',
            status: 204,
            input: false,
            output: false,
            read: true,
            deserialize: false,
            validate: false,
            provider: ActivationProvider::class,
            processor: ActivationProcessor::class,
            name: 'app_yack_api_shop_activation_post',
        ),
    ],
)]
final readonly class Activation
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $token,
    ) {
    }
}
