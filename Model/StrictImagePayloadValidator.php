<?php

declare(strict_types=1);

namespace MarkShust\PolyshellPatch\Model;

use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * OWASP File Upload Cheat Sheet–aligned checks for image payloads:
 * verify file signatures (magic bytes), confirm decoded content type matches the declared MIME,
 * cap raw size and pixel dimensions before decode, then re-encode with GD (required) so only
 * raster data remains (strips appended polyglot bytes, EXIF, etc.).
 *
 * Animated GIFs: GD outputs the first frame only — document for merchants if needed.
 *
 * @see https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
 */
class StrictImagePayloadValidator
{
    /**
     * User-facing invalid-image text (keep in sync with {@see self::getInvalidImagePhrase()}).
     */
    public const FAILURE_MESSAGE = 'The image content is invalid. Verify the content and try again.';

    /** Maximum decoded payload size (bytes) before image APIs run — 5 MiB. */
    public const MAX_DECODED_BYTES = 5242880;

    /**
     * Reject Base64 input longer than this before base64_decode (no valid payload can exceed MAX_DECODED_BYTES).
     * Bound: largest L with ⌊L·3/4⌋ ≤ MAX_DECODED_BYTES.
     */
    public const MAX_BASE64_INPUT_CHARS = 6990507;

    /** Maximum width/height from getimagesize (IHDR etc.) before imagecreatefromstring — mitigates decompression-bomb headers. */
    public const MAX_IMAGE_WIDTH = 10000;

    public const MAX_IMAGE_HEIGHT = 10000;

    private const FORMAT_JPEG = 'jpeg';
    private const FORMAT_PNG = 'png';
    private const FORMAT_GIF = 'gif';

    /**
     * Translated phrase for invalid image payload.
     *
     * @return Phrase
     */
    public function getInvalidImagePhrase(): Phrase
    {
        return __('The image content is invalid. Verify the content and try again.');
    }

    /**
     * Validate and normalize image payload (magic bytes, MIME, caps, GD re-encode).
     *
     * @param string $binary Strict base64-decoded bytes
     * @param string $declaredMime e.g. image/jpeg
     * @param ImageContentInterface $imageContent
     * @return void
     * @throws LocalizedException
     */
    public function assertValidAndNormalize(
        string $binary,
        string $declaredMime,
        ImageContentInterface $imageContent
    ): void {
        if ($binary === '') {
            $this->fail();
        }

        if (strlen($binary) > self::MAX_DECODED_BYTES) {
            $this->fail();
        }

        if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
            $this->fail();
        }

        $this->assertMagicBytesAndParserMimeMatchDeclared($binary, $declaredMime);
        $this->reencodeWithGd($binary, $declaredMime, $imageContent);
    }

    /**
     * Throw standardized invalid-image exception.
     *
     * @return never
     * @throws LocalizedException
     */
    private function fail(): void
    {
        throw new LocalizedException($this->getInvalidImagePhrase());
    }

    /**
     * Verify magic bytes and parser MIME match declared type.
     *
     * List allowed extensions → verify magic bytes; verify file content matches extension (parser MIME vs declared).
     * Parallels OWASP: allowlist + verify file signature + verify content matches type.
     *
     * @param string $binary
     * @param string $declaredMime
     * @return void
     */
    private function assertMagicBytesAndParserMimeMatchDeclared(string $binary, string $declaredMime): void
    {
        $expectedFormat = $this->declaredMimeToFormat($declaredMime);
        if ($expectedFormat === null) {
            $this->fail();
        }

        $magicFormat = $this->detectFormatFromMagicBytes($binary);
        if ($magicFormat === null || $magicFormat !== $expectedFormat) {
            $this->fail();
        }

        $props = getimagesizefromstring($binary);
        if ($props === false || empty($props['mime']) || empty($props[0]) || empty($props[1])) {
            $this->fail();
        }

        if ($this->declaredMimeToFormat($props['mime']) !== $expectedFormat) {
            $this->fail();
        }

        if ($props[0] > self::MAX_IMAGE_WIDTH || $props[1] > self::MAX_IMAGE_HEIGHT) {
            $this->fail();
        }
    }

    /**
     * Map MIME type to internal format key.
     *
     * @param string $mime
     * @return string|null
     */
    private function declaredMimeToFormat(string $mime): ?string
    {
        $mime = strtolower(trim($mime));
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                return self::FORMAT_JPEG;
            case 'image/png':
                return self::FORMAT_PNG;
            case 'image/gif':
                return self::FORMAT_GIF;
            default:
                return null;
        }
    }

    /**
     * Detect image format from magic bytes.
     *
     * @param string $binary
     * @return string|null
     */
    private function detectFormatFromMagicBytes(string $binary): ?string
    {
        if (strlen($binary) < 8) {
            return null;
        }

        if (strncmp($binary, "\xFF\xD8\xFF", 3) === 0) {
            return self::FORMAT_JPEG;
        }

        if (strncmp($binary, "\x89PNG\r\n\x1a\n", 8) === 0) {
            return self::FORMAT_PNG;
        }

        if (strlen($binary) >= 6
            && (strncmp($binary, 'GIF87a', 6) === 0 || strncmp($binary, 'GIF89a', 6) === 0)
        ) {
            return self::FORMAT_GIF;
        }

        return null;
    }

    /**
     * Re-encode with GD and replace base64 on the DTO.
     *
     * @param string $binary
     * @param string $declaredMime
     * @param ImageContentInterface $imageContent
     * @return void
     */
    private function reencodeWithGd(
        string $binary,
        string $declaredMime,
        ImageContentInterface $imageContent
    ): void {
        // phpcs:disable Magento2.Functions.DiscouragedFunction -- GD decode/destroy required for re-encode pipeline
        $image = imagecreatefromstring($binary);
        if ($image === false) {
            $this->fail();
        }

        try {
            $output = $this->encodeGdResourceToDeclaredMime($image, $declaredMime);
        } finally {
            imagedestroy($image);
        }
        // phpcs:enable Magento2.Functions.DiscouragedFunction

        $imageContent->setBase64EncodedData(base64_encode($output));
    }

    /**
     * Encode GD resource to output bytes for the declared MIME type.
     *
     * @param \GdImage|resource $image
     * @param string $declaredMime
     * @return string
     */
    private function encodeGdResourceToDeclaredMime($image, string $declaredMime): string
    {
        // phpcs:disable Magento2.Functions.DiscouragedFunction -- GD output capture requires output buffering and image*()
        ob_start();
        try {
            switch ($declaredMime) {
                case 'image/jpeg':
                case 'image/jpg':
                    if (!imagejpeg($image, null, 92)) {
                        $this->fail();
                    }
                    break;
                case 'image/png':
                    if (!imagepng($image, null, 6)) {
                        $this->fail();
                    }
                    break;
                case 'image/gif':
                    if (!imagegif($image)) {
                        $this->fail();
                    }
                    break;
                default:
                    $this->fail();
            }
            $buffer = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        // phpcs:enable Magento2.Functions.DiscouragedFunction

        if ($buffer === false || $buffer === '') {
            $this->fail();
        }

        return $buffer;
    }
}
