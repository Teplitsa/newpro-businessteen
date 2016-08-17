<?php if(!defined('ABSPATH')) die; // Die if accessed directly

/** Functions of the bot */



add_action('plugins_loaded', function(){

    add_filter('gwptb_default_command_response', function($command_processing_function_name, $comm_data){

        return get_transient('nprtb_comm_admin_'.$comm_data['chat_id'].'_step') ?
            'nprtb_comm_command_response' : $command_processing_function_name;

    }, 11, 2);

    add_filter('gwptb_supported_commnds_list', function($commands){

        $commands = array_merge($commands, array(
            'learn' => 'nprtb_learn_command_response',
            'events' => 'nprtb_events_command_response',
            'comm' => 'nprtb_comm_command_response',
        ));

        return $commands;
    });
});

/* Learn command */
function nprtb_learn_command_response($command_data) {

    delete_transient('nprtb_comm_admin_'.$command_data['chat_id'].'_step');
    delete_transient('nprtb_events_admin_'.$command_data['chat_id'].'_step');

    $result = array('parse_mode' => 'HTML');

    $curr_step = get_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step');

    parse_str($command_data['content'], $command_params);
    if(empty($command_params['topic']) && empty($command_params['level'])) {
        delete_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step');
    }

    if( !get_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step') ) {

        set_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step', 1);

        $result['text'] = 'Пожалуйста, выберите категорию:';

        $result['reply_markup'] = array('inline_keyboard' => array(array(
        )));
        foreach(get_terms(array('taxonomy' => 'topic', 'hide_empty' => false,)) as $topic) {
            $result['reply_markup']['inline_keyboard'][][0] = array(
                'text' => $topic->name,
                'callback_data' => 'learn=1&topic='.$topic->term_id
            );
        }
        $result['reply_markup'] = json_encode($result['reply_markup']);

    } elseif(false !== strpos($command_data['content'], 'topic=') && $curr_step == 1) {

        set_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step', 2);

        $result['text'] = 'ОК, вы выбрали '.get_term($command_params['topic'], 'topic')->name.
            '. Осталось только выбрать ваш уровень:';

        $result['reply_markup'] = json_encode(array('inline_keyboard' => array(array(
            array('text' => 'Начинающий', 'callback_data' => "learn=1&topic={$command_params['topic']}&level=1"),
            array('text' => 'Продвинутый', 'callback_data' => "learn=1&topic={$command_params['topic']}&level=2"),
            array('text' => 'Спец', 'callback_data' => "learn=1&topic={$command_params['topic']}&level=3"),
        ))));

    } elseif(false !== strpos($command_data['content'], 'topic=') && $curr_step >= 2) {

        if(empty($command_params['paged'])) {
            set_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step', 3);
        }

        $per_page = 5; // this will be option.. well, someday )
        $args = array(
            'post_type' => 'knowledge',
            'posts_per_page' => $per_page,
            'paged' => empty($command_params['paged']) ? 1 : (int)$command_params['paged'],
            'tax_query' => array(
		        array(
                    'taxonomy' => 'topic',
                    'field'    => 'term_id',
                    'terms'    => $command_params['topic'],
                ),
            ),
            'meta_query' => array(
                array(
                    'key'     => 'level',
                    'value'   => $command_params['level'],
                    'compare' => '=',
                ),
            ),
        );

        $query = new WP_Query($args);

        if($query->have_posts()) {

            if($query->found_posts > $per_page) {

                $end = ($args['paged']*$per_page < $query->found_posts) ? $args['paged']*$per_page : $query->found_posts;
                $result['text'] = sprintf(__('Found results: %s / displaying %d - %d', 'gwptb'), $query->found_posts, ($args['paged']*$per_page - $per_page) + 1, $end).chr(10).chr(10);

            } else {
                $result['text'] = sprintf(__('Found results: %s', 'gwptb'), $query->found_posts.chr(10).chr(10));
            }


            $result['text'] .= gwptb_format_posts_list($query->posts);
            $result['text'] = apply_filters('gwptb_output_html', $result['text']);

            $result['parse_mode'] = 'HTML';
            $keys = array('inline_keyboard' => array());

            if($query->found_posts > $per_page) {

                if($args['paged'] > 1) {
                    $keys['inline_keyboard'][0][] = array(
                        'text' => __('Previous', 'gwptb'),
                        'callback_data' => 'learn=1&topic='.$command_params['topic'].'&level='.$command_params['level'].'&paged='.($args['paged']-1)
                    );
                }

                if($args['paged'] < ceil($query->found_posts/$per_page)) {
                    $keys['inline_keyboard'][0][] = array(
                        'text' => __('Next', 'gwptb'),
                        'callback_data' => 'learn=1&topic='.$command_params['topic'].'&level='.$command_params['level'].'&paged='.($args['paged']+1)
                    );
                }

            }

            $second_line_index = count($keys['inline_keyboard']) ? 1 : 0;
            $keys['inline_keyboard'][$second_line_index][] = array(
                'text' => 'Выйти из учебных материалов',
                'callback_data' => 'learn=1&reset=1'
            );
            $result['reply_markup'] = json_encode($keys);

        } else {

            $result['text'] = __('Unfortunately your request didn\'t match anything.', 'gwptb');
            $result['text'] = apply_filters('gwptb_output_text', $result['text']);

        }

    } elseif(false !== strpos($command_data['content'], 'reset=')) {

        delete_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step');
        $result['text'] = 'Надеюсь, вам понравились учебные материалы!';
    }

    return $result;
}

/* Learn command */
function nprtb_events_command_response($command_data) {

    delete_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step');
    delete_transient('nprtb_comm_admin_'.$command_data['chat_id'].'_step');

    $result = array('parse_mode' => 'HTML');

    $curr_step = get_transient('nprtb_events_admin_'.$command_data['chat_id'].'_step');

    parse_str($command_data['content'], $command_params);
    if(empty($command_params['topic']) && empty($command_params['level'])) {
        delete_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step');
    }

    if( !get_transient('nprtb_events_admin_'.$command_data['chat_id'].'_step') ) {

        set_transient('nprtb_events_admin_'.$command_data['chat_id'].'_step', 1);

        $result['text'] = 'Пожалуйста, выберите категорию:';

        $result['reply_markup'] = array('inline_keyboard' => array(array(
        )));
        foreach(get_terms(array('taxonomy' => 'topic', 'hide_empty' => false,)) as $topic) {
            $result['reply_markup']['inline_keyboard'][][0] = array(
                'text' => $topic->name,
                'callback_data' => 'events=1&topic='.$topic->term_id
            );
        }
        $result['reply_markup'] = json_encode($result['reply_markup']);

    } elseif(false !== strpos($command_data['content'], 'topic=') && $curr_step == 1) {

        set_transient('nprtb_events_admin_'.$command_data['chat_id'].'_step', 2);

        $result['text'] = 'ОК, вы выбрали '.get_term($command_params['topic'], 'topic')->name.
            '. Где именно вы ищете мероприятия?';

        $result['reply_markup'] = array('inline_keyboard' => array(array(
        )));
        foreach(get_terms(array('taxonomy' => 'city', 'hide_empty' => false,)) as $city) {
            $result['reply_markup']['inline_keyboard'][][0] = array(
                'text' => $city->name,
                'callback_data' => 'events=1&topic='.$command_params['topic'].'&city='.$city->term_id
            );
        }
        $result['reply_markup'] = json_encode($result['reply_markup']);

    } elseif(false !== strpos($command_data['content'], 'topic=') && $curr_step >= 2) {

        if(empty($command_params['paged'])) {
            set_transient('nprtb_events_admin_'.$command_data['chat_id'].'_step', 3);
        }

        $per_page = 5; // this will be option.. well, someday )
        $args = array(
            'post_type' => 'event',
            'posts_per_page' => $per_page,
            'paged' => empty($command_params['paged']) ? 1 : (int)$command_params['paged'],
            'tax_query' => array(
                'relation' => 'AND',
                array(
                    'taxonomy' => 'topic',
                    'field'    => 'term_id',
                    'terms'    => $command_params['topic'],
                ),
                array(
                    'taxonomy' => 'city',
                    'field'    => 'term_id',
                    'terms'    => $command_params['city'],
                ),
            ),
        );
        $query = new WP_Query($args);

        if($query->have_posts()) {

            if($query->found_posts > $per_page) {

                $end = ($args['paged']*$per_page < $query->found_posts) ? $args['paged']*$per_page : $query->found_posts;
                $result['text'] = sprintf(__('Found results: %s / displaying %d - %d', 'gwptb'), $query->found_posts, ($args['paged']*$per_page - $per_page) + 1, $end).chr(10).chr(10);

            } else {
                $result['text'] = sprintf(__('Found results: %s', 'gwptb'), $query->found_posts.chr(10).chr(10));
            }
            $result['text'] .= gwptb_format_posts_list($query->posts);
            $result['text'] = apply_filters('gwptb_output_html', $result['text']);

            $result['parse_mode'] = 'HTML';
            $keys = array('inline_keyboard' => array());

            if($query->found_posts > $per_page) {

                if($args['paged'] > 1) {
                    $keys['inline_keyboard'][0][] = array(
                        'text' => __('Previous', 'gwptb'),
                        'callback_data' => 'events=1&topic='.$command_params['topic'].'&city='.$command_params['city'].'&paged='.($args['paged']-1)
                    );
                }

                if($args['paged'] < ceil($query->found_posts/$per_page)) {
                    $keys['inline_keyboard'][0][] = array(
                        'text' => __('Next', 'gwptb'),
                        'callback_data' => 'events=1&topic='.$command_params['topic'].'&city='.$command_params['city'].'&paged='.($args['paged']+1)
                    );
                }

            }

            $second_line_index = count($keys['inline_keyboard']) ? 1 : 0;
            $keys['inline_keyboard'][$second_line_index][] = array(
                'text' => 'Выйти из поиска мероприятий',
                'callback_data' => 'events=1&reset=1'
            );
            $result['reply_markup'] = json_encode($keys);

        } else {

            $result['text'] = __('Unfortunately your request didn\'t match anything.', 'gwptb');
            $result['text'] = apply_filters('gwptb_output_text', $result['text']);

        }

    } elseif(false !== strpos($command_data['content'], 'reset=')) {

        delete_transient('nprtb_events_admin_'.$command_data['chat_id'].'_step');
        $result['text'] = 'Надеюсь, вам понравились учебные материалы!';
    }

    return $result;
}

/** Communication command */
function nprtb_comm_command_response($command_data) {

    delete_transient('nprtb_learn_admin_'.$command_data['chat_id'].'_step');
    delete_transient('nprtb_events_admin_'.$command_data['chat_id'].'_step');

    $result = array('parse_mode' => 'HTML');
    parse_str($command_data['content'], $command_params);

    if(false !== strpos($command_data['content'], 'comm_type=')) { // Communication request processing

        switch($command_params['comm_type']) {
            case 'admin':
                set_transient('nprtb_comm_admin_'.$command_data['chat_id'].'_step', 1, 3*60*60);
                $result['text'] = 'Пожалуйста, наберите ваше сообщение администраторам бота. Оно будет автоматически отправлено им.';
                break;
            case 'tutors':
                $result['text'] = "Не вопрос! Выбирайте:

<b>1. Влад Минин</b>
Эксперт по праву
vladminin@mail.ru

<b>2. Екатерина Седова</b>
Эксперт в сфере молодежной политике
@katyasedova

<b>3. Ляля Бикчентаева</b>
Исполнительный директор ОО Казанский центр
kzn.junior@gmail.com

<b>4. Ильмир Билалов</b>
Эксперт в сфере молодежного предпринимательства
bilalov2016@mail.ru

<b>5. Нина Гинадьева</b>
Эксперт в сфере Школьных Бизнес Компаниях
ja-rrussia@inbox.ru

<b>6. Солодков Родион</b>
Наставник в сфере интернет-маркетинга
@rojerlab
rodi100@yandex.ru

<b>7. Боглаевская Галина</b>
Эксперт по организации мероприятий
boglaevskay@inbox.ru

<b>8. Хасбутдинова Алина</b>
Эксперт по дизайну
Hasbytdinova.alina@mail.ru

<b>9. Ксения Борисова</b>
Эксперт по менежменту
xeniya.borisova@mail.ru";
                $result['reply_markup'] = json_encode(array('inline_keyboard' => array(array(
                    array('text' => 'К обучению', 'callback_data' => 'learn=1'),
                    array('text' => 'К мероприятиям', 'callback_data' => 'events=1'),
                ))));
                break;
            default:
        }
        return $result;

    } elseif(get_transient('nprtb_comm_admin_'.$command_data['chat_id'].'_step') == 1) {

        $result['text'] = "Спасибо! Админам отправлено сообщение:\n\n«".str_replace(array('@', '/s', Gwptb_Self::get_instance()->get_self_username()), '', $command_data['content']).'»';

        $keys = array();
        $keys['inline_keyboard'][0][] = array('text' => 'Отправить администрации другое сообщение', 'callback_data' => 'comm=1&comm_type=admin');
        $keys['inline_keyboard'][1][] = array('text' => 'Контакты наставников', 'callback_data' => 'comm=1&comm_type=tutors');

        $result['reply_markup'] = json_encode($keys);

        delete_transient('nprtb_comm_admin_'.$command_data['chat_id'].'_step');

    } else {

        $result['text'] = 'С кем именно вы хотите связаться?';
        $result['reply_markup'] = json_encode(array('inline_keyboard' => array(array(
            array('text' => 'Сообщение администрации', 'callback_data' => 'comm=1&comm_type=admin'),
            array('text' => 'Контакты наставников', 'callback_data' => 'comm=1&comm_type=tutors'),
        ))));

        delete_transient('nprtb_comm_admin_'.$command_data['chat_id'].'_step');

    }

    return $result;
}