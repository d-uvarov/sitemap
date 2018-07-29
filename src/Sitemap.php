<?php

namespace d_uvarov;

use XMLWriter;

/**
 * http://www.sitemaps.org/
 *
 * <code>
 *
 * $sitemap = new Sitemap('http://example.com', __DIR__, 'sitemap.xml');
 *
 * $sitemap->addUrl('/link1');
 * $sitemap->addUrl('/link2', time());
 * $sitemap->addUrl('/link3', time(), Sitemap::CHANGEFREQ_HOURLY);
 * $sitemap->addUrl('/link4', time(), Sitemap::CHANGEFREQ_DAILY, 0.3);
 * $sitemap->addUrl('/link5', time(), Sitemap::CHANGEFREQ_DAILY, 0.3);
 * $sitemap->write();
 *
 * </code>
 */
class Sitemap
{
    const XMLNS     = 'xmlns';
    const XMLNS_VAL = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    const CHANGEFREQ_ALWAYS  = 'always';
    const CHANGEFREQ_HOURLY  = 'hourly';
    const CHANGEFREQ_DAILY   = 'daily';
    const CHANGEFREQ_WEEKLY  = 'weekly';
    const CHANGEFREQ_MONTHLY = 'monthly';
    const CHANGEFREQ_YEARLY  = 'yearly';
    const CHANGEFREQ_NEVER   = 'never';

    /**
     * @var integer
     */
    protected $maxUrls = 50000;

    /**
     * @var integer
     */
    protected $urlsCount = 0;

    /**
     * @var string
     */
    protected $partFile;

    /**
     * @var string
     */
    protected $sitemapFileName;

    /**
     * @var string
     */
    protected $workDir;

    /**
     * @var integer
     */
    protected $writtenFileCount = 0;

    /**
     * @var array
     */
    protected $writtenFilePaths = [];

    /**
     * @var integer
     */
    protected $bufferSize = 1000;

    /**
     * @var array
     */
    protected $validFrequencies = [
        self::CHANGEFREQ_ALWAYS,
        self::CHANGEFREQ_HOURLY,
        self::CHANGEFREQ_DAILY,
        self::CHANGEFREQ_WEEKLY,
        self::CHANGEFREQ_MONTHLY,
        self::CHANGEFREQ_YEARLY,
        self::CHANGEFREQ_NEVER,
    ];

    /**
     * @var XMLWriter
     */
    protected $writer;

    /**
     * @var bool
     */
    protected $gzipped = true;

    /**
     * @var string
     */
    protected $siteUrl;

    /**
     * Sitemap constructor.
     *
     * @param string $siteUrl
     * @param string $workDir
     * @param string $fileName
     *
     * @throws \Exception
     */
    public function __construct(string $siteUrl, string $workDir, string $fileName)
    {
        if (false === filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid url');
        }

        if ($this->gzipped && !extension_loaded('zlib')) {
            throw new \Exception('Extension zlib is not loaded');
        }

        $filePart = $workDir . 'sitemap_part.xml';

        $this->siteUrl         = $siteUrl;
        $this->writer          = $this->getXmlWriter();
        $this->partFile        = $filePart;
        $this->sitemapFileName = $fileName;
        $this->workDir         = $workDir;

        $this->createNewSitemapFile();
    }

    /**
     *
     */
    protected function createNewSitemapFile()
    {
        $this->writtenFileCount++;
        $currentFilePath = $this->getCurrentFilePath();

        $this->writtenFilePaths[] = $currentFilePath;

        if (file_exists($currentFilePath)) {
            unlink($currentFilePath);
        }

        $this->writer->startElement('urlset');
        $this->writer->writeAttribute(self::XMLNS, self::XMLNS_VAL);
    }

    /**
     *
     */
    protected function finishFile()
    {
        if ($this->writer !== null) {
            $this->writer->endElement();
            $this->writer->endDocument();
            $this->flush();
        }
    }

    /**
     *
     */
    public function write()
    {
        $this->finishFile();

        $writer = $this->getXmlWriter();
        $writer->startElement('sitemapindex');
        $writer->writeAttribute(self::XMLNS, self::XMLNS_VAL);

        if ($this->gzipped) {
            foreach ($this->getSitemapFilePaths() as $path) {
                $gzFile = $this->createGzip($path, $this->siteUrl);

                if ($gzFile) {
                    $writer->startElement('sitemap');
                    $writer->writeElement('loc', $gzFile);
                    $writer->writeElement('lastmod', date('c'));
                    $writer->endElement();
                }
            }
        } else {
            foreach ($this->getSitemapFilePaths() as $path) {
                $writer->startElement('sitemap');
                $writer->writeElement('loc', $this->siteUrl . DIRECTORY_SEPARATOR . basename($path));
                $writer->writeElement('lastmod', date('c'));
                $writer->endElement();
            }
        }

        $writer->endElement();
        $writer->endDocument();
        file_put_contents($this->workDir . $this->sitemapFileName, $writer->outputMemory());
    }

    /**
     *
     */
    protected function flush()
    {
        if (!file_put_contents($this->getCurrentFilePath(), $this->writer->outputMemory(), FILE_APPEND)) {
            throw new \RuntimeException('Could not write buffer to file');
        }
    }

    /**
     * Adds a new url to sitemap
     *
     * @param string  $location
     * @param integer $lastModified
     * @param float   $changeFrequency
     * @param string  $priority
     *
     * @throws \InvalidArgumentException
     */
    public function addUrl($location, $lastModified = null, $changeFrequency = null, $priority = null)
    {
        if ($this->urlsCount == $this->maxUrls) {
            $this->finishFile();
            $this->createNewSitemapFile();
        }

        if ($this->urlsCount == $this->bufferSize) {
            $this->flush();
        }

        $this->writer->startElement('url');

        if (mb_strlen($location) > 1 && $location[0] != DIRECTORY_SEPARATOR) {
            $location = DIRECTORY_SEPARATOR . $location;
        }

        $this->writer->writeElement('loc', $this->siteUrl . $location);

        if (null !== $lastModified) {
            $this->writer->writeElement('lastmod', date('c', $lastModified));
        }

        if (null !== $changeFrequency) {
            if (!in_array($changeFrequency, $this->validFrequencies, true)) {
                throw new \InvalidArgumentException('Invalid changeFrequency');
            }

            $this->writer->writeElement('changefreq', $changeFrequency);
        }

        if (null !== $priority) {
            if (!is_numeric($priority) || $priority < 0 || $priority > 1) {
                throw new \InvalidArgumentException('Invalid priority');
            }
            $this->writer->writeElement('priority', $priority);
        }

        $this->writer->endElement();

        $this->urlsCount++;
    }

    /**
     * @return string
     */
    protected function getCurrentFilePath()
    {
        if ($this->writtenFileCount < 2) {
            return $this->partFile;
        }

        $parts = pathinfo($this->partFile);

        return $parts['dirname'] . DIRECTORY_SEPARATOR
            . $parts['filename'] . '_' . $this->writtenFileCount . '.' . $parts['extension'];
    }

    /**
     * @param string $baseUrl
     *
     * @return array
     */
    public function getSitemapUrls($baseUrl)
    {
        $urls = [];
        foreach ($this->writtenFilePaths as $file) {
            $urls[] = $baseUrl . pathinfo($file, PATHINFO_BASENAME);
        }

        return $urls;
    }

    /**
     * @param integer $number
     */
    public function setMaxUrls(int $number)
    {
        $this->maxUrls = $number;
    }

    /**
     * @param integer $number
     */
    public function setBufferSize(int $number)
    {
        $this->bufferSize = $number;
    }

    /**
     * @return array
     */
    public function getSitemapFilePaths()
    {
        return $this->writtenFilePaths;
    }

    /**
     * @param string $filename
     * @param string $baseUrl
     *
     * @return string
     */
    public function createGzip(string $filename, string $baseUrl)
    {
        $error = true;

        if (!empty($filename) && !empty($baseUrl)) {
            if ($fpGz = gzopen($filename . '.gz', 'wb')) {
                if ($fp = fopen($filename, 'rb')) {
                    while (!feof($fp)) {
                        gzwrite($fpGz, fread($fp, 524288));
                    }

                    fclose($fp);
                    unlink($filename);
                    $error = false;
                }

                gzclose($fpGz);
            }
        }

        return !$error ? $baseUrl . DIRECTORY_SEPARATOR . pathinfo($filename . '.gz', PATHINFO_BASENAME) : null;
    }

    /**
     * @return bool
     */
    public function isGzipped(): bool
    {
        return $this->gzipped;
    }

    /**
     * @param bool $gzipped
     */
    public function setGzipped(bool $gzipped)
    {
        $this->gzipped = $gzipped;
    }

    /**
     * @return XMLWriter
     */
    protected function getXmlWriter()
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);

        return $writer;
    }
}