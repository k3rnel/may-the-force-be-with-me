<?php /** @noinspection PhpVoidFunctionResultUsedInspection */

use Model\Analytics_model;
use Model\Boosterpack_model;
use Model\Comment_model;
use Model\Login_model;
use Model\Post_model;
use Model\User_model;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 10.11.2018
 * Time: 21:36
 */
class Main_page extends MY_Controller
{

    public function __construct()
    {

        parent::__construct();

        if (is_prod())
        {
            die('In production it will be hard to debug! Run as development environment!');
        }
    }

    public function index()
    {
        $user = User_model::get_user();

        App::get_ci()->load->view('main_page', ['user' => User_model::preparation($user, 'default')]);
    }

    public function get_all_posts()
    {
        $posts =  Post_model::preparation_many(Post_model::get_all(), 'default');
        return $this->response_success(['posts' => $posts]);
    }

    public function get_boosterpacks()
    {
        $posts =  Boosterpack_model::preparation_many(Boosterpack_model::get_all(), 'default');
        return $this->response_success(['boosterpacks' => $posts]);
    }

    /**
     * @throws \Exception
     */
    public function login()
    {
        // TODO: task 1, аутентификация

        if (User_model::is_logged()) {
            return $this->response_error('already_logged_in');
        }

        $login = $this->input->post('login');
        $password = $this->input->post('password');

        $errors = [];
        if (empty($login)) {
            $errors['info'][] = 'Login is required';
        }
        if (empty($password)) {
            $errors['info'][] = 'Password is required';
        }

        if (count($errors)) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_WRONG_PARAMS, $errors, 400);
        }

        $user = Login_model::login($login, $password);

        return $this->response_success(['user' => $user]);
    }

    public function logout()
    {
        // TODO: task 1, аутентификация

        if (!User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        Login_model::logout();

        return redirect(site_url('/'));
    }

    /**
     * @throws \Exception
     */
    public function comment()
    {
        // TODO: task 2, комментирование

        if (!User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $data = $this->input->post();

        $errors = [];

        if (empty($data['commentText'])) {
            $errors['info'][] = 'CommentText is required';
        }
        if (empty($data['postId'])) {
            $errors['info'][] = 'PostId is required';
        } else {
            $post = Post_model::get_by_id($data['postId']);
            if ($post->is_loaded() === false) {
                $errors['info'][] = 'Given post not found';
            }
        }

        if (count($errors)) {
            return $this->response_error(
                System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR, $errors, 400);
        }

        $user = User_model::get_user();

        $comment = Comment_model::create([
            'user_id'   => $user->get_id(),
            'assign_id' => $data['postId'],
            'text'      => $data['commentText'],
        ]);

        return $this->response_success([
            'comment' => $comment->object_beautify()
        ]);
    }

    public function like_comment(int $comment_id)
    {
        if (!User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $comment = Comment_model::get_by_id($comment_id);

        if ($comment->is_loaded() === false) {
            return $this->response_error('error_core_internal', [], 404);
        }

        $user = User_model::get_user();

        if ($user->get_likes_balance() <= 0) {
            return $this->response_error('error_core_internal', [
                'info' => ['likes_balance' => 'Not enough']
            ], 400);
        }

        try {
            App::get_s()->start_trans()->execute();

            $comment->increment_likes($user);
        } catch (Exception $ex) {
            App::get_s()->rollback()->execute();

            return $this->response_error(
                System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR, [
                'info' => ['comment' => 'Not save']
            ], 400);
        }

        App::get_s()->commit()->execute();

        return $this->response_success([
            'likes' => $comment->get_likes()
        ]);
    }

    public function like_post(int $post_id)
    {
        // TODO: task 3, лайк поста

        if (!User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $post = Post_model::get_by_id($post_id);

        if ($post->is_loaded() === false) {
            return $this->response_error('error_core_internal', [], 404);
        }

        $user = User_model::get_user();
        if ($user->get_likes_balance() <= 0) {
            return $this->response_error('error_core_internal', [
                'info' => ['likes_balance' => 'Not enough']
            ], 400);
        }

        try {
            App::get_s()->start_trans()->execute();
            $post->increment_likes($user);
        } catch (Exception $ex) {
            App::get_s()->rollback()->execute();

            return $this->response_error(
                System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR, [
                'info' => ['comment' => 'Not save']
            ], 400);
        }

        App::get_s()->commit()->execute();

        return $this->response_success([
            'likes' => $post->get_likes()
        ]);
    }

    public function add_money()
    {
        // TODO: task 4, пополнение баланса

        if (!User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        $sum = (float) $this->input->post('sum');

        $user = User_model::get_user();

        if (!$user->add_money($sum)) {
            return $this->response_error(
                System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR, [
                'info' => ['comment' => 'Not save']
            ], 400);
        }

//        Что-то на подобии этого. С загрузкой класса проблемы, не успеваю решить эту проблему
//        Analytics_model::create([
//            'user_id' => $user->get_id(),
//            'object'  => Model\Enum\Transaction_info::TOP_UP_WALLET,
//            'action'  => Model\Enum\Transaction_type::TOP_UP_ACTION,
//            'amount'  => $sum
//        ]);

        return $this->response_success(['balance' => $user->get_wallet_balance()]);
    }

    /**
     * @throws \Exception
     */
    public function get_post(int $post_id)
    {
        // TODO получения поста по id

        $post = Post_model::preparation(Post_model::get_by_id($post_id), 'full_info');

        return $this->response_success([
            'post' => $post
        ]);
    }

    public function buy_boosterpack()
    {
        // Check user is authorize
        if (!User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        // TODO: task 5, покупка и открытие бустерпака

        if ($this->input->post('id') == null) {
            return $this->response_error(
                System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR, [], 404);
        }

        $user = User_model::get_user();

        $boosterpack = Boosterpack_model::get_by_id($this->input->post('id'));

        if ($user->get_wallet_balance() < $boosterpack->get_price()) {
            return $this->response_error(
                System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR, [
                'info' => ['wallet_balance' => 'Not enough']
            ], 400);
        }

        try {
            $boosterpack_info = $boosterpack->open();
            $item = $boosterpack_info->get_item();

            $likes = $item->get_price();
            $result = $user->remove_money($boosterpack->get_price());

            if ($result === true) {
                $user->set_likes_balance($user->get_likes_balance() + $likes);
            }
        } catch (Exception $ex) {
            return $this->response_error('error_core_internal', [
                'info' => ['user' => $ex->getMessage()]
            ], 404);
        }

        return $this->response_success(['amount' => $likes]);
    }





    /**
     * @return object|string|void
     */
    public function get_boosterpack_info(int $bootserpack_info)
    {
        // Check user is authorize
        if ( ! User_model::is_logged())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }


        //TODO получить содержимое бустерпака
    }
}
