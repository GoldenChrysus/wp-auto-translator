<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Integration;

use WpAutoTranslator\PostAssociator;
use WpAutoTranslator\TranslationRepository;
use WpAutoTranslator\TranslatorLock;

final class TranslationFlowTest extends \WP_UnitTestCase
{
    private TranslationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TranslationRepository();
    }

    public function test_posts_stay_untagged_until_a_base_locale_exists(): void
    {
        $postId = self::factory()->post->create(['post_title' => 'Before setup']);

        $this->assertNull($this->repository->findByPostId($postId));
    }

    public function test_existing_posts_are_tagged_when_the_base_locale_is_chosen(): void
    {
        $postId = self::factory()->post->create([
            'post_title' => 'Hello',
            'post_content' => 'World',
            'post_status' => 'publish',
        ]);

        update_option('wat_base_locale', 'en_US');
        $count = (new PostAssociator($this->repository))->associateExisting('en_US');

        $this->assertSame(1, $count);
        $record = $this->repository->findByPostId($postId);
        $this->assertNotNull($record);
        $this->assertTrue($record->isOriginal());
        $this->assertSame('en_US', $record->locale);
    }

    public function test_a_published_post_is_queued_when_auto_translate_is_on(): void
    {
        update_option('wat_base_locale', 'en_US');
        update_option('wat_target_locales', ['ja']);
        update_option('wat_auto_translate', '1');

        $postId = self::factory()->post->create([
            'post_title' => 'Hello',
            'post_content' => '<p>Hello</p>',
            'post_status' => 'publish',
        ]);

        $original = $this->repository->findByPostId($postId);
        $this->assertNotNull($original);
        $translation = $this->repository->findByGroupAndLocale($original->groupId, 'ja');
        $this->assertNotNull($translation);
        $this->assertSame('pending', $translation->status);
        $this->assertSame($postId, $translation->sourcePostId);
        $this->assertNotSame($postId, $translation->postId);
    }

    public function test_rest_and_wp_query_can_filter_by_locale(): void
    {
        update_option('wat_base_locale', 'en_US');
        update_option('wat_target_locales', ['ja']);

        $english = self::factory()->post->create([
            'post_title' => 'English',
            'post_status' => 'publish',
        ]);
        $original = $this->repository->findByPostId($english);
        $this->assertNotNull($original);

        TranslatorLock::enable();
        $japanese = self::factory()->post->create([
            'post_title' => 'Japanese',
            'post_status' => 'publish',
        ]);
        TranslatorLock::disable();

        $this->repository->insert(
            $original->groupId,
            $japanese,
            'ja',
            $english,
            'translated',
            $original->sourceHash
        );

        $byLocale = new \WP_REST_Request('GET', '/wp/v2/posts');
        $byLocale->set_query_params(['lang' => 'ja']);
        $japaneseResponse = rest_get_server()->dispatch($byLocale);
        $this->assertSame(200, $japaneseResponse->get_status());
        $japaneseIds = wp_list_pluck($japaneseResponse->get_data(), 'id');
        $this->assertContains($japanese, $japaneseIds);
        $this->assertNotContains($english, $japaneseIds);
        $match = null;
        foreach ($japaneseResponse->get_data() as $post) {
            if ((int) $post['id'] === $japanese) {
                $match = $post;
            }
        }
        $this->assertNotNull($match);
        $this->assertSame('ja', $match['lang']);

        $all = new \WP_REST_Request('GET', '/wp/v2/posts');
        $allResponse = rest_get_server()->dispatch($all);
        $allIds = wp_list_pluck($allResponse->get_data(), 'id');
        $this->assertContains($english, $allIds);
        $this->assertNotContains($japanese, $allIds);

        $unknown = new \WP_REST_Request('GET', '/wp/v2/posts');
        $unknown->set_query_params(['lang' => 'zz']);
        $this->assertSame(400, rest_get_server()->dispatch($unknown)->get_status());

        $query = new \WP_Query([
            'lang' => 'ja',
            'post_type' => 'post',
            'posts_per_page' => 20,
            'post_status' => 'publish',
        ]);
        $this->assertContains($japanese, wp_list_pluck($query->posts, 'ID'));
        $this->assertNotContains($english, wp_list_pluck($query->posts, 'ID'));
    }

    public function test_slug_with_lang_accepts_the_base_slug_and_returns_the_translation(): void
    {
        update_option('wat_base_locale', 'en_US');

        $english = self::factory()->post->create([
            'post_title' => 'My post',
            'post_name' => 'my-post',
            'post_status' => 'publish',
        ]);
        $original = $this->repository->findByPostId($english);
        $this->assertNotNull($original);

        TranslatorLock::enable();
        $japanese = self::factory()->post->create([
            'post_title' => '私のポスト',
            'post_name' => 'watashi-no-posuto',
            'post_status' => 'publish',
        ]);
        $french = self::factory()->post->create([
            'post_title' => 'Mon article',
            'post_name' => 'mon-article',
            'post_status' => 'publish',
        ]);
        TranslatorLock::disable();

        $this->repository->insert($original->groupId, $japanese, 'ja', $english, 'translated', $original->sourceHash);
        $this->repository->insert($original->groupId, $french, 'fr_FR', $english, 'translated', $original->sourceHash);

        $baseLocale = new \WP_REST_Request('GET', '/wp/v2/posts');
        $baseLocale->set_query_params(['slug' => 'my-post', 'lang' => 'en_US']);
        $baseLocaleIds = array_map('intval', wp_list_pluck(rest_get_server()->dispatch($baseLocale)->get_data(), 'id'));
        $this->assertSame([$english], $baseLocaleIds);

        $fromBaseSlug = new \WP_REST_Request('GET', '/wp/v2/posts');
        $fromBaseSlug->set_query_params(['slug' => 'my-post', 'lang' => 'ja']);
        $baseResponse = rest_get_server()->dispatch($fromBaseSlug);
        $this->assertSame(200, $baseResponse->get_status());
        $baseIds = array_map('intval', wp_list_pluck($baseResponse->get_data(), 'id'));
        $this->assertSame([$japanese], $baseIds);

        $fromTranslatedSlug = new \WP_REST_Request('GET', '/wp/v2/posts');
        $fromTranslatedSlug->set_query_params(['slug' => 'watashi-no-posuto', 'lang' => 'ja']);
        $translatedIds = array_map('intval', wp_list_pluck(rest_get_server()->dispatch($fromTranslatedSlug)->get_data(), 'id'));
        $this->assertSame([$japanese], $translatedIds);

        $otherLocaleSlug = new \WP_REST_Request('GET', '/wp/v2/posts');
        $otherLocaleSlug->set_query_params(['slug' => 'mon-article', 'lang' => 'ja']);
        $this->assertSame([], rest_get_server()->dispatch($otherLocaleSlug)->get_data());
    }
}
