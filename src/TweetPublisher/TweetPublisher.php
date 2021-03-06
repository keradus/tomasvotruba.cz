<?php declare(strict_types=1);

namespace TomasVotruba\Website\TweetPublisher;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symplify\PackageBuilder\Console\Style\SymfonyStyleFactory;
use TomasVotruba\Website\TweetPublisher\TwitterApi\TwitterApiWrapper;

/**
 * @inspire https://gist.github.com/petrvacha/28ec8f5eac39283f1e7dce350f5a65ad
 * @thanks Petr Vacha
 */
final class TweetPublisher
{
    /**
     * @var int
     */
    private $minimalGapInDays;

    /**
     * @var PostTweetsProvider
     */
    private $postTweetsProvider;

    /**
     * @var TwitterApiWrapper
     */
    private $twitterApiWrapper;

    /**
     * @var SymfonyStyle
     */
    private $symfonyStyle;

    public function __construct(
        int $minimalGapInDays,
        PostTweetsProvider $postTweetsProvider,
        TwitterApiWrapper $twitterApiWrapper
    ) {
        $this->minimalGapInDays = $minimalGapInDays;
        $this->postTweetsProvider = $postTweetsProvider;
        $this->twitterApiWrapper = $twitterApiWrapper;
        $this->symfonyStyle = SymfonyStyleFactory::create();
    }

    public function run(): void
    {
        $daysSinceLastTweet = $this->twitterApiWrapper->getDaysSinceLastTweet();
        if ($daysSinceLastTweet < $this->minimalGapInDays) {
            $this->symfonyStyle->warning(sprintf(
                'It is only %d days since last tweet. Minimal gap is %d days, so no tweet until then.',
                $daysSinceLastTweet,
                $this->minimalGapInDays
            ));
            return;
        }

        $tweetsToPublish = $this->excludePublishedTweets(
            $this->postTweetsProvider->provide(),
            $this->twitterApiWrapper->getPublishedTweets()
        );

        if (! count($tweetsToPublish)) {
            $this->symfonyStyle->warning(
                'There is no new tweet to publish. Add a new one to one of your post under "tweet:" option.'
            );
            return;
        }

        $tweet = $this->pickTweetCandidate($tweetsToPublish);

        $this->tweet($tweet);

        $this->symfonyStyle->success(sprintf(
            'Tweet "%s" was succesfully published.',
            $tweet['text']
        ));
    }

    /**
     * @param string[][] $allTweets
     * @param string[][] $publishedTweets
     * @return string[][]
     */
    private function excludePublishedTweets(array $allTweets, array $publishedTweets): array
    {
        $unpublishedTweets = [];

        foreach ($allTweets as $tweet) {
            foreach ($publishedTweets as $publishedTweet) {
                if ($tweet['text'] === $publishedTweet['text']) {
                    continue 2;
                }
            }

            $unpublishedTweets[] = $tweet;
        }

        return $unpublishedTweets;
    }

    /**
     * @param string[][] $tweetsToPublish
     * @return string[]
     */
    private function pickTweetCandidate(array $tweetsToPublish): array
    {
        $randomKey = array_rand($tweetsToPublish);
        return $tweetsToPublish[$randomKey];
    }

    /**
     * @param mixed[] $tweet
     */
    private function tweet(array $tweet): void
    {
        if ($tweet['image']) {
            $this->twitterApiWrapper->publishTweetWithImage($tweet['text'], $tweet['image']);
        } else {
            $this->twitterApiWrapper->publishTweet($tweet['text']);
        }
    }
}
