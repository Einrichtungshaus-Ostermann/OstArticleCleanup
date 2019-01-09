<?php declare(strict_types=1);

/**
 * Einrichtungshaus Ostermann GmbH & Co. KG - Article Cleanup
 *
 * @package   OstArticleCleanup
 *
 * @author    Eike Brandt-Warneke <e.brandt-warneke@ostermann.de>
 * @copyright 2018 Einrichtungshaus Ostermann GmbH & Co. KG
 * @license   proprietary
 */

namespace OstArticleCleanup\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeactivateArticlesCommand extends ShopwareCommand
{
    /**
     * ...
     *
     * @var array
     */
    private $configuration;

    /**
     * The maximum difference in percent between currently active articles
     * and articles within the .csv. If the difference is too high, it might
     * be a faulty .csv file and we abort the process.
     *
     * @var array
     */
    private $maxDifference = 10;

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        parent::__construct();
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // get the files via configuration
        $files = explode(PHP_EOL, $this->configuration['csvFiles']);

        // every article number from csv
        $csv = [];

        // loop every file
        foreach ($files as $file) {
            $output->writeln('processing csv: ' . $file);

            $content = file_get_contents($file);

            $articles = 0;
            $i = 0;

            // split by \n and loop every line
            foreach (explode(PHP_EOL, $content) as $line) {
                // always add
                $i++;

                // ignore first line
                if ($i === 1) {
                    // next
                    continue;
                }

                // get as csv
                $arr = str_getcsv($line, ';', '"');

                // force the number
                $number = (string) $arr[0];

                // empty number?!
                if (empty($number)) {
                    // ignore it
                    continue;
                }

                // add article number
                array_push($csv, $arr[0]);
                ++$articles;
            }

            $output->writeln('articles processed: ' . $articles);
        }

        // unique them
        $unique = array_unique($csv);
        $unique = array_values($unique);

        // short output
        $output->writeln('unique articles: ' . count($unique));

        // count currently active articles
        $query = '
            SELECT COUNT(*)
            FROM s_articles_details
            WHERE active = 1
        ';
        $activeArticles = (int) Shopware()->Db()->fetchOne($query);

        // output them
        $output->writeln('active articles: ' . $activeArticles);

        // calculate the difference as percentage value
        $diff = round(((max(count($unique), $activeArticles) / min(count($unique), $activeArticles)) - 1) * 100, 2);

        // output
        $output->writeln('difference: ' . $diff . '%');

        // max difference reached?!
        if ($diff > $this->maxDifference) {
            // stop processing
            $output->writeln('max difference of ' . $this->maxDifference . '% exceeded - abort further execution');

            return;
        }

        // get every online article number
        $query = '
            SELECT ordernumber, ordernumber
            FROM s_articles_details
            WHERE active = 1
        ';
        $online = array_values(Shopware()->Db()->fetchPairs($query));

        // start the progress bar
        $progressBar = new ProgressBar($output, count($online));
        $progressBar->setRedrawFrequency(10);
        $progressBar->start();

        // every deactivated article
        $deactivated = [];

        // loop every online article
        foreach ($online as $number) {
            // is this article not within any of the csv?
            if (!in_array($number, $unique)) {
                // deactivate it
                $query = '
                    UPDATE s_articles_details
                    SET active = 0
                    WHERE ordernumber = :number
                ';
                Shopware()->Db()->query($query, ['number' => $number]);

                // count it
                array_push($deactivated, $number);
            }

            // advance progress bar
            $progressBar->advance();
        }

        // done
        $progressBar->finish();

        // and final output
        $output->writeln('articles deactivated: ' . implode(', ', $deactivated));
    }
}
