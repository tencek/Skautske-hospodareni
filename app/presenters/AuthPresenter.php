<?php

class AuthPresenter extends BasePresenter {

    protected function startup() {
        parent::startup();
    }

    /**
     * pokud je uziatel uz prihlasen, staci udelat referesh
     * @param string $backlink 
     * @param bool $final - je to konečné přesměrování? použít při problémem se zacyklením
     */
    public function actionDefault($backlink, $final = FALSE) {
        if (Environment::getUser()->isLoggedIn()) {
            if ($backlink) {
                $this->getApplication()->restoreRequest($backlink);
            }
        }
        $this->redirect(':Default:');
    }

    /**
     * přesměruje na stránku s přihlášením
     * @param type $backlink 
     */
    function actionLogOnSkautIs($backlink = NULL) {
        $this->redirectUrl($this->context->authService->getLoginUrl($backlink));
    }

    /**
     * zajistuje spracovani prihlaseni na skautIS
     * @param string $ReturnUrl 
     */
    function actionSkautIS($ReturnUrl = NULL) {
        $uservice = new UserService();
        $post = $this->request->post;
        if (!isset($post['skautIS_Token'])) { //pokud není nastavený token, tak zde nemá co dělat
            $this->redirect(":Default:");
        }
        try {
            $this->context->authService->setInit(array(
                "token" => $post['skautIS_Token'],
                "roleId" => $post['skautIS_IDRole'],
                "unitId" => $post['skautIS_IDUnit']
            ));

            if (!$this->context->userService->isLoggedIn()) {
                throw new SkautIS_AuthenticationException("Nemáte platné přihlášení do skautISu");
            }
            $me = $this->context->userService->getUserData();

            $this->user->setExpiration('+ 29 minutes'); // nastavíme expiraci
            $this->user->setAuthenticator(new SkautISAuthenticator());
            $this->user->login($me);

            if (isset($ReturnUrl)) {
                $this->context->application->restoreRequest($ReturnUrl);
            }
        } catch (SkautIS_AuthenticationException $e) {
            $this->flashMessage($e->getMessage(), "fail");
            $this->redirect(":Auth:");
        }
        $this->presenter->redirect(':Accountancy:Default:');
    }

    /**
     * zajištuje odhlašení ze skautISu
     * SkautIS sem přesměruje po svém odhlášení
     */
    function actionSkautisLogout() {
        $this->user->logout(TRUE);
        if ($this->request->post['skautIS_Logout']) {
            $this->presenter->flashMessage("Byl jsi úspěšně odhlášen.");
        } else {
            $this->presenter->flashMessage("Odhlášení ze skautISu se nezdařilo", "fail");
        }
        $this->redirect(":Default:");
        //$this->redirectUrl($this->service->getLogoutUrl());
    }

}
