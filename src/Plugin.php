<?php

namespace Weirdan\DoctrinePsalmPlugin;

use Composer\Semver\Semver;
use OutOfBoundsException;
use PackageVersions\Versions;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

use function array_merge;
use function array_search;
use function class_exists;
use function glob;

class Plugin implements PluginEntryPointInterface
{
    /** @return void */
    public function __invoke(RegistrationInterface $psalm, ?SimpleXMLElement $config = null)
    {
        $stubs = $this->getStubFiles();
        $stubs = array_merge($stubs, $this->getBundleStubs());
        foreach ($stubs as $file) {
            $psalm->addStubFile($file);
        }
    }

    /** @return string[] */
    private function getStubFiles(): array
    {
        $files = array_merge(
            glob(__DIR__ . '/../stubs/*.phpstub') ?: [],
            glob(__DIR__ . '/../stubs/DBAL/*.phpstub') ?: []
        );

        if ($this->hasPackageOfVersion('doctrine/collections', '>= 1.6.0')) {
            unset($files[array_search(__DIR__ . '/../stubs/ArrayCollection.phpstub', $files, true)]);
        }

        return $files;
    }

    /** @return string[] */
    private function getBundleStubs(): array
    {
        if (! $this->hasPackage('doctrine/doctrine-bundle')) {
            return [];
        }

        return glob(__DIR__ . '/../' . 'bundle-stubs/*.phpstub');
    }

    private function hasPackage(string $packageName): bool
    {
        try {
            $this->getPackageVersion($packageName);
        } catch (OutOfBoundsException $e) {
            return false;
        }

        return true;
    }

    /**
     * @throws OutOfBoundsException
     */
    private function getPackageVersion(string $packageName): string
    {
        if (class_exists(Versions::class)) {
            return (string) Versions::getVersion($packageName);
        }

        throw new OutOfBoundsException();
    }

    private function hasPackageOfVersion(string $packageName, string $constraints): bool
    {
        $packageVersion = $this->getPackageVersion($packageName);
        if (false !== strpos($packageVersion, '@')) {
            [$packageVersion] = explode('@', $packageVersion);
        }

        if (0 === strpos($packageVersion, 'dev-')) {
            $packageVersion = '9999999-dev';
        }

        return Semver::satisfies($packageVersion, $constraints);
    }
}
