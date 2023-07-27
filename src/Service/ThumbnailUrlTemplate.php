<?php declare(strict_types=1);

namespace Frosh\ThumbnailProcessorImgProxy\Service;

use Frosh\ThumbnailProcessor\Service\SalesChannelIdDetector;
use Frosh\ThumbnailProcessor\Service\ThumbnailUrlTemplateInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ThumbnailUrlTemplate implements ThumbnailUrlTemplateInterface
{
    private ?array $config = null;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly ThumbnailUrlTemplateInterface $parent,
        private readonly SalesChannelIdDetector $salesChannelIdDetector
    ) {
    }

    public function getUrl(string $mediaUrl, string $mediaPath, string $width): string
    {
        $config = $this->getConfig();

        if (empty($config)) {
            return $this->parent->getUrl($mediaUrl, $mediaPath, $width);
        }

        $extension = pathinfo($mediaPath, \PATHINFO_EXTENSION);
        $encodedUrl = rtrim(strtr(base64_encode($mediaUrl . '/' . $mediaPath), '+/', '-_'), '=');

        $path = "/rs:{$config['resizingType']}:{$width}:0:{$config['enlarge']}/g:{$config['gravity']}/{$encodedUrl}.{$extension}";
        $signature = hash_hmac('sha256', $config['saltBin'] . $path, $config['keyBin'], true);

        if ($config['signatureSize'] !== 32) {
            $signature = pack('A' . $config['signatureSize'], $signature);
        }

        $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return \rtrim($config['Domain'], '/') . '/' . $signature . $path;
    }

    /**
     * @return array{Domain: string, imgproxykey: string, imgproxysalt: string, keyBin: string, saltBin: string, resizingType: string, gravity: string, enlarge: int, signatureSize: int}
     */
    private function getConfig(): array
    {
        if (!is_array($this->config)) {
            $salesChannelId = $this->salesChannelIdDetector->getSalesChannelId();
            $config = $this->systemConfigService->get('FroshPlatformThumbnailProcessorImgProxy.config', $salesChannelId);

            if (!\is_array($config)) {
                return [];
            }

            if (empty($config['Domain']) || empty($config['imgproxykey']) || empty($config['imgproxysalt'])) {
                return [];
            }

            $config['keyBin'] = pack('H*', $config['imgproxykey']);
            $config['saltBin'] = pack('H*', $config['imgproxysalt']);

            if (empty($config['resizingType'])) {
                $config['resizingType'] = 'fit';
            }

            if (empty($config['gravity'])) {
                $config['gravity'] = 'sm';
            }

            if (!isset($config['enlarge'])) {
                $config['enlarge'] = 0;
            }

            if (empty($config['signatureSize'])) {
                $config['signatureSize'] = 32;
            }

            if (!\is_int($config['signatureSize'])) {
                $config['signatureSize'] = (int) $config['signatureSize'];
            }

            $this->config = $config;
        }

        return $this->config;
    }
}
