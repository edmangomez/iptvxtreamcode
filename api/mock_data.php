<?php

function getMockData(string $action = '', array $extra = []): array {
    // user_info (sin action)
    if ($action === '') {
        return [
            'user_info' => [
                'username'          => 'demo_user',
                'password'          => '***',
                'auth'              => 1,
                'status'            => 'Active',
                'exp_date'          => (string)(time() + 86400 * 25),
                'is_trial'          => '0',
                'active_connections' => '1',
                'max_connections'    => '3',
                'created_at'        => (string)(time() - 86400 * 60),
            ],
            'server_info' => [
                'url'             => 'demo.xtream-server.com',
                'port'            => '8080',
                'https_port'      => '443',
                'server_protocol' => 'http',
                'timestamp_now'   => time(),
                'time_now'        => date('Y-m-d H:i:s'),
            ],
        ];
    }

    return match ($action) {
        'get_live_categories' => [
            ['category_id' => '1', 'category_name' => 'Deportes',       'parent_id' => '0', 'count' => '12'],
            ['category_id' => '2', 'category_name' => 'Noticias',       'parent_id' => '0', 'count' => '8'],
            ['category_id' => '3', 'category_name' => 'Entretenimiento', 'parent_id' => '0', 'count' => '15'],
            ['category_id' => '4', 'category_name' => 'Infantil',       'parent_id' => '0', 'count' => '6'],
            ['category_id' => '5', 'category_name' => 'Documentales',   'parent_id' => '0', 'count' => '9'],
            ['category_id' => '6', 'category_name' => 'Música',         'parent_id' => '0', 'count' => '10'],
            ['category_id' => '7', 'category_name' => 'Latinoamérica',  'parent_id' => '0', 'count' => '14'],
            ['category_id' => '8', 'category_name' => 'Religión',       'parent_id' => '0', 'count' => '4'],
        ],

        'get_live_streams' => getMockStreams($extra['category_id'] ?? null),

        'get_vod_categories' => [
            ['category_id' => '10', 'category_name' => 'Acción',      'count' => '25'],
            ['category_id' => '11', 'category_name' => 'Comedia',     'count' => '20'],
            ['category_id' => '12', 'category_name' => 'Drama',       'count' => '18'],
            ['category_id' => '13', 'category_name' => 'Terror',      'count' => '12'],
            ['category_id' => '14', 'category_name' => 'Ciencia Ficción', 'count' => '15'],
            ['category_id' => '15', 'category_name' => 'Animación',   'count' => '22'],
        ],

        'get_vod_streams' => getMockVod($extra['category_id'] ?? null),

        'get_series_categories' => [
            ['category_id' => '20', 'category_name' => 'Drama',    'count' => '8'],
            ['category_id' => '21', 'category_name' => 'Comedia',  'count' => '6'],
            ['category_id' => '22', 'category_name' => 'Crimen',   'count' => '5'],
            ['category_id' => '23', 'category_name' => 'Ciencia Ficción', 'count' => '4'],
        ],

        'get_series' => getMockSeries($extra['category_id'] ?? null),

        'get_series_info' => getMockSeriesInfo((int)($extra['series_id'] ?? 0)),

        'get_vod_info' => [
            'info' => [
                'movie_data' => [
                    'name'               => 'Película de prueba',
                    'cover_big'          => 'https://picsum.photos/seed/vod1/400/600',
                    'duration'           => '2h 15min',
                    'rating'             => '8.2',
                    'year'              => '2024',
                    'genre'             => 'Acción, Aventura',
                    'plot'              => 'Una emocionante historia de acción y aventura que mantendrá al espectador al borde del asiento.',
                    'director'          => 'Director Ejemplo',
                    'cast'              => 'Actor 1, Actor 2, Actor 3',
                ],
                'container_extension' => 'mp4',
            ],
        ],

        'get_short_epg' => getMockEpg((int)($extra['stream_id'] ?? 0)),

        default => [],
    };
}

function getMockStreams(?string $catId): array {
    $all = [
        ['stream_id' => 101, 'name' => 'ESPN',       'stream_icon' => 'https://img.icons8.com/color/48/espn.png',       'category_id' => '1', 'epg_channel_id' => 'ESPN.us'],
        ['stream_id' => 102, 'name' => 'Fox Sports',  'stream_icon' => 'https://img.icons8.com/color/48/fox.png',        'category_id' => '1', 'epg_channel_id' => 'FOXSPORTS.us'],
        ['stream_id' => 103, 'name' => 'CNN',         'stream_icon' => 'https://img.icons8.com/color/48/cnn.png',        'category_id' => '2', 'epg_channel_id' => 'CNN.us'],
        ['stream_id' => 104, 'name' => 'BBC World',   'stream_icon' => 'https://img.icons8.com/color/48/bbc.png',        'category_id' => '2', 'epg_channel_id' => 'BBCWORLD.uk'],
        ['stream_id' => 105, 'name' => 'HBO',          'stream_icon' => 'https://img.icons8.com/color/48/hbo.png',       'category_id' => '3', 'epg_channel_id' => 'HBO.us'],
        ['stream_id' => 106, 'name' => 'Netflix TV',   'stream_icon' => 'https://img.icons8.com/color/48/netflix.png',   'category_id' => '3', 'epg_channel_id' => 'NETFLIX.us'],
        ['stream_id' => 107, 'name' => 'Cartoon Network', 'stream_icon' => 'https://img.icons8.com/color/48/cartoon-network.png', 'category_id' => '4'],
        ['stream_id' => 108, 'name' => 'Disney Channel',   'stream_icon' => 'https://img.icons8.com/color/48/disney.png', 'category_id' => '4'],
        ['stream_id' => 109, 'name' => 'National Geographic', 'stream_icon' => 'https://img.icons8.com/color/48/national-geographic.png', 'category_id' => '5'],
        ['stream_id' => 110, 'name' => 'Discovery Channel',    'stream_icon' => 'https://img.icons8.com/color/48/discovery.png', 'category_id' => '5'],
        ['stream_id' => 111, 'name' => 'MTV',          'stream_icon' => 'https://img.icons8.com/color/48/mtv.png',        'category_id' => '6'],
        ['stream_id' => 112, 'name' => 'VH1',          'stream_icon' => 'https://img.icons8.com/color/48/musical.png',   'category_id' => '6'],
        ['stream_id' => 113, 'name' => 'TNT',          'stream_icon' => 'https://img.icons8.com/color/48/tnt.png',        'category_id' => '7'],
        ['stream_id' => 114, 'name' => 'Telenovela Channel', 'stream_icon' => 'https://img.icons8.com/color/48/telenovela.png', 'category_id' => '7'],
        ['stream_id' => 115, 'name' => 'EWTN',         'stream_icon' => '', 'category_id' => '8'],
    ];

    if ($catId) {
        return array_values(array_filter($all, fn($s) => $s['category_id'] === $catId));
    }
    return $all;
}

function getMockVod(?string $catId): array {
    $all = [];
    $titles = [
        10 => ['Misión Imposible', 'Rápido y Furioso', 'John Wick', 'Mad Max', 'Gladiador'],
        11 => ['Son como Niños', 'Supercool', 'Mi Pobre Angelito', 'Ted', 'Bridgerton'],
        12 => ['El Padrino', 'Forrest Gump', 'La Lista de Schindler', 'Interestelar', 'Parásitos'],
        13 => ['El Conjuro', 'It', 'El Exorcista', 'Hereditary', 'La Monja'],
        14 => ['Star Wars', 'Matrix', 'Blade Runner', 'Dune', 'Avatar'],
        15 => ['Toy Story', 'Buscando a Nemo', 'Frozen', 'Shrek', 'Coco'],
    ];

    $i = 201;
    foreach ($titles as $cid => $movies) {
        foreach ($movies as $title) {
            $all[] = [
                'stream_id'          => $i++,
                'name'               => $title,
                'stream_icon'        => 'https://picsum.photos/seed/' . $i . '/400/600',
                'rating'             => (string)(7 + round(lcg_value() * 2, 1)),
                'rating_5based'      => round(3.5 + lcg_value() * 1.5, 1),
                'year'              => (string)(2018 + rand(0, 6)),
                'category_id'        => (string)$cid,
                'container_extension' => 'mp4',
            ];
        }
    }

    if ($catId) {
        return array_values(array_filter($all, fn($s) => $s['category_id'] === $catId));
    }
    return $all;
}

function getMockSeries(?string $catId): array {
    $all = [
        ['series_id' => 301, 'name' => 'Breaking Bad',     'cover' => 'https://picsum.photos/seed/bb/400/600', 'rating' => '9.5', 'year' => '2008', 'category_id' => '20'],
        ['series_id' => 302, 'name' => 'The Crown',        'cover' => 'https://picsum.photos/seed/crown/400/600', 'rating' => '8.7', 'year' => '2016', 'category_id' => '20'],
        ['series_id' => 303, 'name' => 'The Office',       'cover' => 'https://picsum.photos/seed/office/400/600', 'rating' => '8.9', 'year' => '2005', 'category_id' => '21'],
        ['series_id' => 304, 'name' => 'Friends',          'cover' => 'https://picsum.photos/seed/friends/400/600', 'rating' => '8.8', 'year' => '1994', 'category_id' => '21'],
        ['series_id' => 305, 'name' => 'True Detective',   'cover' => 'https://picsum.photos/seed/td/400/600', 'rating' => '8.9', 'year' => '2014', 'category_id' => '22'],
        ['series_id' => 306, 'name' => 'Narcos',           'cover' => 'https://picsum.photos/seed/narcos/400/600', 'rating' => '8.8', 'year' => '2015', 'category_id' => '22'],
        ['series_id' => 307, 'name' => 'Stranger Things',  'cover' => 'https://picsum.photos/seed/st/400/600', 'rating' => '8.7', 'year' => '2016', 'category_id' => '23'],
        ['series_id' => 308, 'name' => 'Black Mirror',     'cover' => 'https://picsum.photos/seed/bm/400/600', 'rating' => '8.8', 'year' => '2011', 'category_id' => '23'],
    ];

    if ($catId) {
        return array_values(array_filter($all, fn($s) => $s['category_id'] === $catId));
    }
    return $all;
}

function getMockSeriesInfo(int $id): array {
    $series = [
        301 => ['name' => 'Breaking Bad', 'plot' => 'Un profesor de química diagnosticado con cáncer terminal se adentra en el mundo del crimen para asegurar el futuro de su familia.', 'cast' => 'Bryan Cranston, Aaron Paul', 'director' => 'Vince Gilligan', 'genre' => 'Drama, Crimen', 'rating' => '9.5', 'year' => '2008'],
        302 => ['name' => 'The Crown', 'plot' => 'Sigue la vida de la Reina Isabel II desde su matrimonio en 1947 hasta la actualidad.', 'cast' => 'Claire Foy, Olivia Colman', 'director' => 'Peter Morgan', 'genre' => 'Drama, Historia', 'rating' => '8.7', 'year' => '2016'],
        303 => ['name' => 'The Office', 'plot' => 'Una comedia sobre la vida cotidiana en una oficina de venta de papel.', 'cast' => 'Steve Carell, Jenna Fischer', 'director' => 'Greg Daniels', 'genre' => 'Comedia', 'rating' => '8.9', 'year' => '2005'],
        304 => ['name' => 'Friends', 'plot' => 'Seis amigos navegan por la vida y el amor en la ciudad de Nueva York.', 'cast' => 'Jennifer Aniston, Courteney Cox', 'director' => 'David Crane', 'genre' => 'Comedia', 'rating' => '8.8', 'year' => '1994'],
        305 => ['name' => 'True Detective', 'plot' => 'Detectives investigan crímenes oscuros a lo largo de diferentes líneas temporales.', 'cast' => 'Matthew McConaughey, Woody Harrelson', 'director' => 'Nic Pizzolatto', 'genre' => 'Crimen, Drama', 'rating' => '8.9', 'year' => '2014'],
        306 => ['name' => 'Narcos', 'plot' => 'La historia real del auge y caída del cártel de drogas de Pablo Escobar.', 'cast' => 'Wagner Moura, Pedro Pascal', 'director' => 'Chris Brancato', 'genre' => 'Crimen, Drama', 'rating' => '8.8', 'year' => '2015'],
        307 => ['name' => 'Stranger Things', 'plot' => 'Un grupo de niños descubre fenómenos sobrenaturales en su pequeño pueblo.', 'cast' => 'Millie Bobby Brown, Finn Wolfhard', 'director' => 'The Duffer Brothers', 'genre' => 'Ciencia Ficción, Terror', 'rating' => '8.7', 'year' => '2016'],
        308 => ['name' => 'Black Mirror', 'plot' => 'Una serie antológica que explora un futuro distópico donde la tecnología tiene consecuencias inesperadas.', 'cast' => 'Variado', 'director' => 'Charlie Brooker', 'genre' => 'Ciencia Ficción, Drama', 'rating' => '8.8', 'year' => '2011'],
    ];

    $info = $series[$id] ?? $series[301];
    $cover = 'https://picsum.photos/seed/series' . $id . '/400/600';

    return [
        'info' => [
            'name'    => $info['name'],
            'cover'   => $cover,
            'plot'    => $info['plot'],
            'cast'    => $info['cast'],
            'director' => $info['director'],
            'genre'   => $info['genre'],
            'rating'  => $info['rating'],
            'year'    => $info['year'],
            'category_id' => '20',
        ],
        'episodes' => [
            '1' => [
                ['id' => 401, 'episode_num' => 1, 'title' => 'Episodio Piloto',       'container_extension' => 'mp4', 'info' => ['duration' => '45 min']],
                ['id' => 402, 'episode_num' => 2, 'title' => 'El Comienzo',           'container_extension' => 'mp4', 'info' => ['duration' => '48 min']],
                ['id' => 403, 'episode_num' => 3, 'title' => 'Decisiones',            'container_extension' => 'mp4', 'info' => ['duration' => '42 min']],
                ['id' => 404, 'episode_num' => 4, 'title' => 'Consecuencias',         'container_extension' => 'mp4', 'info' => ['duration' => '46 min']],
                ['id' => 405, 'episode_num' => 5, 'title' => 'El Conflicto',          'container_extension' => 'mp4', 'info' => ['duration' => '44 min']],
            ],
            '2' => [
                ['id' => 406, 'episode_num' => 1, 'title' => 'Nuevos Comienzos',     'container_extension' => 'mp4', 'info' => ['duration' => '47 min']],
                ['id' => 407, 'episode_num' => 2, 'title' => 'Secretos',              'container_extension' => 'mp4', 'info' => ['duration' => '43 min']],
                ['id' => 408, 'episode_num' => 3, 'title' => 'Revelaciones',          'container_extension' => 'mp4', 'info' => ['duration' => '45 min']],
            ],
            '3' => [
                ['id' => 409, 'episode_num' => 1, 'title' => 'El Final del Camino',   'container_extension' => 'mp4', 'info' => ['duration' => '50 min']],
                ['id' => 410, 'episode_num' => 2, 'title' => 'Redención',             'container_extension' => 'mp4', 'info' => ['duration' => '48 min']],
            ],
        ],
    ];
}

function getMockEpg(int $streamId): array {
    $now = time();
    $programs = [
        ['title' => 'Noticiero Estelar',      'description' => 'Resumen informativo con las noticias más importantes del día.'],
        ['title' => 'Película de la Tarde',    'description' => 'Disfruta del mejor cine en la comodidad de tu hogar.'],
        ['title' => 'Serie de Noche',          'description' => 'Capítulo completo de la serie más vista del momento.'],
        ['title' => 'Documental Salvaje',     'description' => 'Explora la naturaleza en su estado más puro.'],
        ['title' => 'Talk Show Nocturno',     'description' => 'Entrevistas y debates con los personajes más relevantes.'],
        ['title' => 'Música en Vivo',         'description' => 'Los mejores conciertos y presentaciones en vivo.'],
        ['title' => 'Deportes: En Vivo',       'description' => 'Transmisión en vivo del evento deportivo más importante.'],
    ];

    $listings = [];
    $start = $now - 3600;
    foreach ($programs as $i => $p) {
        $listings[] = [
            'id'          => $streamId * 100 + $i,
            'epg_id'      => (string)($streamId * 100 + $i),
            'title'       => $p['title'],
            'start'       => (string)$start,
            'end'         => (string)($start + 3600),
            'description' => $p['description'],
        ];
        $start += 3600;
    }

    return ['epg_listings' => $listings];
}
