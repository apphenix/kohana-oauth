kohana-oauth
============

servier side authorization for facebook, vkontakte, odnoklassniki fkr kohana framework
//example of controller
<?php

defined('SYSPATH') or die('No direct script access.');

class Controller_Oauth extends Controller_Common {

  public function action_get_data()
  {
    $profile = Session::instance()->get('profile');
    if(!$profile)
    {
      throw new Kohana_Exception('no user data input');
    }
    $validError = Session::instance()->get_once('validError',array());
    $view    = View::factory('user/needed_data_test');
    $this->template->sitetitle = 'Доп информация';
    $this->template->content = $view
      ->bind('validError', $validError)
      ->bind('profile',$profile);
    if($this->request->method() == Request::POST)
    {
      $pass = substr(md5(uniqid(rand(), false)), 3, 8);
      $data = array(
          'username'         => substr(md5(uniqid(rand(), false)), 2, 7),
          'password'         => $pass,
          'password_confirm' => $pass,
          'register_date'    => time(),
          'mail_comfirm'     => 1,
      );
      //adding fields that are diffrent
      switch ($profile->referer)
      {
        case 'facebook':
          $data = array_merge($data, array(
              'name'  => $profile->name,
              'fb_id' => $profile->id,
              'email' => $profile->email,
            ), $this->request->post());
          //field to update if user already exists
          $update = array('fb_id' => $data['fb_id']);
          break;
        case 'vkontakte':
          $data   = array_merge($data, array(
              'name'  => $profile->first_name.' '.$profile->last_name,
              'vk_id' => $profile->uid,
            ), $this->request->post());
          //field to update if user already exists
          $update = array('vk_id' => $data['vk_id']);
          break;
        case 'odnoklassniki':
          $data   = array_merge($data, array(
              'name'  => $profile->first_name.' '.$profile->last_name,
              'od_id' => $profile->uid,
            ), $this->request->post());
          //field to update if user already exists
          $update = array('od_id' => $data['od_id']);
          break;
        default:
          throw new Kohana_Exception('No such action referer');
          break;
      }

      $expected          = array(
          'name',
          'username',
          'password',
          'register_date',
          'fb_id',
          'vk_id',
          'od_id',
          'email',
          'city',
          'mail_comfirm'
      );
      try
      {
        $user = ORM::factory('user')->create_user($data, $expected);
      }
      catch (ORM_Validation_Exception $e)
      {
        $user = ORM::factory('user')->where('email', '=', $data['email'])->find();
        if ($user)
        {
          $user->values($update)->update();
          $this->auth->force_login($user);
          $this->request->redirect('/user/account');
        }
        Session::instance()->set('validError', $e->errors('model'));
        $this->request->redirect(Request::current()->uri());
      }
      $user->add('roles', ORM::factory('role', array('name' => 'login')));
      $this->auth->force_login($user);
      $this->request->redirect('/user/account');
    }

  }
  //Bacref for Vkontakte login
  public function action_vk_login()
  {
    $vk = Oauth::instance('vkontakte');
    if ($vk->login())
    {
      $profile = $vk->get_user();
      if ($profile)
      {
        $user = ORM::factory('user')
          ->where('vk_id', '=', $profile->uid)
          ->find();
        if ($user->id)
        {
          $user->values(array('vk_id'        => $profile->uid, 'mail_comfirm' => 1))->update();
          $this->auth->force_login($user);
          $this->request->redirect('/user/account');
        }
        else
        {
          $profile->referer = 'vkontakte';
          Session::instance()->set('profile', $profile);
          $this->request->redirect('/oauth/get_data');
        }
      }
    }

  }
  //Backref for Odnoklassniiki login
  public function action_od_login()
  {
    $od = Oauth::instance('odnoklassniki');
    if ($od->login())
    {
      $profile = $od->get_user();
      if ($profile)
      {
        $user = ORM::factory('user')
          ->where('od_id', '=', $profile->uid)
          ->find();
        if ($user->id)
        {
          $user->values(array('od_id'        => $profile->uid, 'mail_comfirm' => 1))->update();
          $this->auth->force_login($user);
          $this->request->redirect('/user/account');
        }
        else
        {
          $profile->referer = 'odnoklassniki';
          Session::instance()->set('profile', $profile);
          $this->request->redirect('/oauth/get_data');
        }
      }
    }

  }
//Backref for Facebook login
  public function action_fb_login()
  {
    $fb = Oauth::instance('facebook');
    if ($fb->login())
    {
      $profile = $fb->get_user();
      if ($profile)
      {
        $user = ORM::factory('user')
          ->where('fb_id', '=', $profile->id)
          ->or_where('email', '=', $profile->email)
          ->find();
        //Login if we already have this user
        if ($user->id)
        {
          $user->values(array('fb_id'        => $profile->id, 'mail_comfirm' => 1))->update();
          $this->auth->force_login($user);
          $this->request->redirect('/user/account');
        }
        //Save Facebook user data and redirect to
        else
        {
          $profile->referer = 'facebook';
          Session::instance()->set('profile', $profile);
          $this->request->redirect('/oauth/get_data');
        }


      }
    }

  }

}