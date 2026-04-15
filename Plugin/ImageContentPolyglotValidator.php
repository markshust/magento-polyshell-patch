<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Plugin;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\ImageContentValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Phrase;

class ImageContentPolyglotValidator
{
    private const DANGEROUS_MARKERS = [
        '<?php',
        '<?=',
        '<? ',
        'eval(',
        'base64_decode',
        'shell_exec',
        'system(',
        'passthru(',
        'assert(',
        'phar://',
        'GIF89a;<?',
    ];

    public function afterIsValid(
        ImageContentValidator $subject,
        bool $result,
        ImageContentInterface $imageContent
    ): bool {
        if ($result === false) {
            return false;
        }

        $base64 = (string) $imageContent->getBase64EncodedData();
        if ($base64 === '') {
            return $result;
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new InputException(new Phrase('Invalid image payload encoding.'));
        }

        $haystack = strtolower($decoded);
        foreach (self::DANGEROUS_MARKERS as $marker) {
            if (strpos($haystack, strtolower($marker)) !== false) {
                throw new InputException(
                    new Phrase('The uploaded image content is not allowed.')
                );
            }
        }

        return $result;
    }
}
