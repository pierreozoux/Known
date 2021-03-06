<?php

    namespace IdnoPlugins\IndiePub\Pages\MicroPub {

        use Idno\Common\ContentType;
        use Idno\Entities\User;
        use IdnoPlugins\IndiePub\Pages\IndieAuth\Token;

        class Endpoint extends \Idno\Common\Page
        {

            function get($params = array())
            {

                $headers = $this->getallheaders();
                if (!empty($headers['Authorization'])) {
                    $token = $headers['Authorization'];
                    $token = trim(str_replace('Bearer', '', $token));
                } else if ($token = $this->getInput('access_token')) {
                    $token = trim($token);
                }

                if ($this->validateToken($token)) {
                    if ($query = trim($this->getInput('q'))) {
                        switch ($query) {
                            case 'syndicate-to':
                                echo json_encode([
                                    'syndicate-to'          => \Idno\Core\Idno::site()->syndication()->getServiceAccountStrings(),
                                    'syndicate-to-expanded' => \Idno\Core\Idno::site()->syndication()->getServiceAccountData()
                                ], JSON_PRETTY_PRINT);
                                break;
                        }
                    }
                }
                else {
                    $this->setResponse(403);
                    echo '?';
                }
            }

            function post()
            {

                $headers = $this->getallheaders();
                if (!empty($headers['Authorization'])) {
                    $token = $headers['Authorization'];
                    $token = trim(str_replace('Bearer', '', $token));
                } else if ($token = $this->getInput('access_token')) {
                    $token = trim($token);
                }

                if ($this->validateToken($token)) {
                    // If we're here, we're authorized

                    // Get details
                    $type        = $this->getInput('h');
                    $content     = $this->getInput('content');
                    $name        = $this->getInput('name');
                    $in_reply_to = $this->getInput('in-reply-to');
                    $syndicate   = $this->getInput('mp-syndicate-to');
                    $like_of     = $this->getInput('like-of');
                    $repost_of   = $this->getInput('repost-of');

                    if ($type == 'entry') {
                        $type = 'article';
                        if (!empty($_FILES['photo'])) {
                            $type = 'photo';
                            if (empty($name) && !empty($content)) {
                                $name    = $content;
                                $content = '';
                            }
                        }
                        if (empty($name)) {
                            $type = 'note';
                        }
                        if (!empty($like_of)) {
                            $type = 'like';
                        }
                        if (!empty($repost_of)) {
                            $type = 'repost';
                        }
                    }

                    // Get an appropriate plugin, given the content type
                    if ($contentType = ContentType::getRegisteredForIndieWebPostType($type)) {

                        if ($entity = $contentType->createEntity()) {

                            error_log(var_export($entity, true));

                            if (is_array($content)) {
                                $content_value = '';
                                if (!empty($content['html'])) {
                                    $content_value = $content['html'];
                                } else if (!empty($content['value'])) {
                                    $content_value = $content['value'];
                                }
                            } else {
                                $content_value = $content;
                            }

                            $this->setInput('title', $name);
                            $this->setInput('body', $content_value);
                            $this->setInput('inreplyto', $in_reply_to);
                            $this->setInput('like-of', $like_of);
                            $this->setInput('repost-of', $repost_of);
                            $this->setInput('access', 'PUBLIC');
                            if ($created = $this->getInput('published')) {
                                $this->setInput('created', $created);
                            }
                            if (!empty($syndicate)) {
                                $syndication = array(trim(str_replace('.com', '', $syndicate)));
                                $this->setInput('syndication', $syndication);
                            }
                            if ($entity->saveDataFromInput()) {
                                $this->setResponse(201);
                                header('Location: ' . $entity->getURL());
                                exit;
                            } else {
                                $this->setResponse(500);
                                echo "Couldn't create {$type}";
                                exit;
                            }

                        }

                    } else {

                        $this->setResponse(500);
                        echo "Couldn't find content type {$type}";
                        exit;

                    }

                }

                $this->setResponse(403);
                echo 'Bad token';

            }

            // Check that this token is either a user token or the
            // site's API token, and log that user in if so.
            private function validateToken($token)
            {
                if (!empty($token)) {
                    $found = Token::findUserForToken($token);
                    if (!empty($found)) {
                        $user = $found['user'];
                        \Idno\Core\Idno::site()->session()->refreshSessionUser($user);
                        return true;
                    }
                    $user = \Idno\Entities\User::getOne(array('admin' => true));
                    if ($token == $user->getAPIkey()) {
                        \Idno\Core\Idno::site()->session()->refreshSessionUser($user);
                        return true;
                    }
                }
                return false;
            }

        }
    }
