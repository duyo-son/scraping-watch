<?php

declare(strict_types=1);

use WatchScraper\Scraper\Sites\AlluScraper;
use WatchScraper\Scraper\Sites\BestVintageScraper;
use WatchScraper\Scraper\Sites\BluekScraper;
use WatchScraper\Scraper\Sites\CommitGinzaScraper;
use WatchScraper\Scraper\Sites\CouronneScraper;
use WatchScraper\Scraper\Sites\GinzaRasinScraper;
use WatchScraper\Scraper\Sites\GmtScraper;
use WatchScraper\Scraper\Sites\HousekihirobaScraper;
use WatchScraper\Scraper\Sites\JackroadScraper;
use WatchScraper\Scraper\Sites\KameKichiScraper;
use WatchScraper\Scraper\Sites\KomehyoScraper;
use WatchScraper\Scraper\Sites\LipsScraper;
use WatchScraper\Scraper\Sites\MoonphaseScraper;
use WatchScraper\Scraper\Sites\OkuraScraper;
use WatchScraper\Scraper\Sites\RodeoDriveScraper;

return [
    ['name' => 'COMMIT GINZA', 'url' => 'https://commit-watch.co.jp/collections/vacheron-constantin?sort_by=manual&filter.v.availability=1&filter.v.price.gte=&filter.v.price.lte=', 'category' => '최우선', 'enabled' => true, 'scraper' => CommitGinzaScraper::class],
    ['name' => 'GMT', 'url' => 'https://www.gmt-j.com/search?itemwear=0&installments=0&specpanelopen=0&maker=288&stockstatus=1#itemlist', 'category' => '최우선', 'enabled' => true, 'scraper' => GmtScraper::class],
    ['name' => 'GINZA RASIN', 'url' => 'https://www.rasin.co.jp/SHOP/13614/list.html', 'category' => '최우선', 'enabled' => true, 'scraper' => GinzaRasinScraper::class],
    ['name' => 'Jackroad', 'url' => 'https://www.jackroad.co.jp/shop/r/rjwva/', 'category' => '최우선', 'enabled' => true, 'scraper' => JackroadScraper::class],
    ['name' => 'Kame-Kichi', 'url' => 'https://www.kame-kichi.com/brands/VACHERON_CONSTANTIN?op=STOCK#items', 'category' => '최우선', 'enabled' => true, 'scraper' => KameKichiScraper::class],
    ['name' => 'LIPS', 'url' => 'https://lips-online.jp/ec/products/list?mode=&category_id=23&name=&pageno=&disp_number=40&orderby=2&price_from=&price_to=&tag_id%5B%5D=&select_tag=&tenpozaiko%5B%5D=&stock_flg%5B%5D=1&name_andor=&teido%5B%5D=&model%5B%5D=&shozaimei%5B%5D=&price_down_flg%5B%5D=&teido_flg=', 'category' => '최우선', 'enabled' => true, 'scraper' => LipsScraper::class],
    ['name' => 'BEST VINTAGE', 'url' => 'https://ishida-watch.com/c/bestvintage/v_brand/v_vacheron-constantin', 'category' => '최우선', 'enabled' => true, 'scraper' => BestVintageScraper::class],
    ['name' => 'ALLU', 'url' => 'https://allu-official.com/jp/ja/collections/vacheronconstantin-traditionnelle/?type=1,2&stock=1', 'category' => '우선', 'enabled' => true, 'scraper' => AlluScraper::class],
    ['name' => 'OKURA', 'url' => 'https://ec.wb-ookura.com/collections/vacheron?view=20260722205803', 'category' => '우선', 'enabled' => true, 'scraper' => OkuraScraper::class],
    ['name' => 'Housekihiroba', 'url' => 'https://housekihiroba.jp/shop/c/c01vc/', 'category' => '우선', 'enabled' => true, 'scraper' => HousekihirobaScraper::class],
    ['name' => 'KOMEHYO', 'url' => 'https://komehyo.jp/vacheronconstantin/', 'category' => '우선', 'enabled' => true, 'scraper' => KomehyoScraper::class],
    ['name' => 'Rodeo Drive', 'url' => 'https://www.rodeodrive.co.jp/shop/goods/search.aspx?narrow6=%E3%83%B4%E3%82%A1%E3%82%B7%E3%83%A5%E3%83%AD%E3%83%B3%E3%82%B3%E3%83%B3%E3%82%B9%E3%82%BF%E3%83%B3%E3%82%BF%E3%83%B3', 'category' => '우선', 'enabled' => true, 'scraper' => RodeoDriveScraper::class],
    ['name' => 'Couronne', 'url' => 'https://www.couronne.info/view/search?search_keyword=&search_name=&search_price_low=&search_price_high=&search_category=vacheron-constantin&search_original_code=', 'category' => '우선', 'enabled' => true, 'scraper' => CouronneScraper::class],
    ['name' => 'MOONPHASE', 'url' => 'https://moon-phase.jp/collections/vacheronconstantin', 'category' => '우선', 'enabled' => true, 'scraper' => MoonphaseScraper::class],
    ['name' => 'BLUEK', 'url' => 'https://www.bluek.co.jp/c/brand-all/brands-a/vacheron-constantin/vacheron-constantin-watch', 'category' => '우선', 'enabled' => true, 'scraper' => BluekScraper::class],
];
