<?php
/**
 * Simple FreeAdmin Login Plugin
 *
 * @category Genaker
 * @package  Genaker_FreeAdmin
 * @author   Genaker
 */

namespace Genaker\FreeAdmin\Plugin\Backend\Model\Auth;

use Magento\Backend\Model\Auth;
use Magento\User\Model\UserFactory;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Module\ModuleList;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;

/**
 * Simple plugin to allow free admin login when Auth module is disabled
 */
class SimpleLoginPlugin
{
    /**
     * @var UserFactory
     */
    private $userFactory;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var ModuleList
     */
    private $moduleList;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * Constructor
     *
     * @param UserFactory $userFactory
     * @param DeploymentConfig $deploymentConfig
     * @param ModuleList $moduleList
     * @param State $appState
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        UserFactory $userFactory,
        DeploymentConfig $deploymentConfig,
        ModuleList $moduleList,
        State $appState,
        ManagerInterface $eventManager
    ) {
        $this->userFactory = $userFactory;
        $this->deploymentConfig = $deploymentConfig;
        $this->moduleList = $moduleList;
        $this->appState = $appState;
        $this->eventManager = $eventManager;
    }

    /**
     * Before plugin for login method
     *
     * @param Auth $subject
     * @param string $username
     * @param string $password
     * @return array
     */
    public function beforeLogin(Auth $subject, $username, $password)
    {
        
        // Check if Auth module is disabled using Magento classes
        if ($this->isAuthModuleDisabled()) {
           
            // Log the bypass attempt
            error_log("FreeAdmin: Authentication bypassed for user: $username");
            
            // Use the same working approach as the original Auth.php
            $this->bypassAuthentication($subject, $username);
            
            // Return empty credentials to prevent normal auth
            return ['', ''];
        }

        // Auth module is enabled, proceed with normal authentication
        return [$username, $password];
    }

    /**
     * Check if Auth module is disabled and mode is not production
     *
     * @return bool
     */
    private function isAuthModuleDisabled()
    {
        try {
            // Check if we're in production mode - if so, never bypass auth
            $mode = $this->appState->getMode();
            if ($mode === State::MODE_PRODUCTION) {
                return false;
            }
            return $this->deploymentConfig->get('backend/auth') === false;
        } catch (\Exception $e) {
            // If we can't check, assume auth is enabled for security
            return false;
        }
    }

    /**
     * Bypass authentication using the same working approach as original Auth.php
     *
     * @param Auth $subject
     * @param string $username
     * @return void
     */
    private function bypassAuthentication(Auth $subject, $username)
    {
        try {
            // Use the same working approach as the original Auth.php
            $userModel = $this->userFactory->create();
            $adminUser = null;

            // First try to find admin user by email/username if provided
            if (!empty($username)) {
                $adminUser = $userModel->getCollection()
                    ->addFieldToFilter('email', $username)
                    ->getFirstItem();
                
                // If not found by email, try by username
                if (!$adminUser || !$adminUser->getId()) {
                    $adminUser = $userModel->getCollection()
                        ->addFieldToFilter('username', $username)
                        ->getFirstItem();
                }
            }

            // If no user found by email/username, get the first admin user
            if (!$adminUser || !$adminUser->getId()) {
                $adminUser = $userModel->getCollection()->getFirstItem();
            }

            if ($adminUser && $adminUser->getId()) {
                // Use the same working approach: set credential storage and auth storage
                $this->setCredentialStorageAndLogin($subject, $adminUser);
                error_log("FreeAdmin: Authentication bypassed using user: " . $adminUser->getEmail());
            } else {
                error_log("FreeAdmin: No admin user found in the system");
            }
        } catch (\Exception $e) {
            error_log('FreeAdmin: Error bypassing authentication: ' . $e->getMessage());
        }
    }

    /**
     * Set credential storage and login using public methods
     *
     * @param Auth $subject
     * @param \Magento\User\Model\User $user
     * @return void
     */
    private function setCredentialStorageAndLogin(Auth $subject, $user)
    {
        try {
            // Try to get credential storage - if not available, we'll use a different approach
            $credentialStorage = $subject->getCredentialStorage();
            
            if ($credentialStorage) {
                // Set the admin user data in credential storage
                $credentialStorage->setData($user->getData());
                $credentialStorage->setId($user->getId());
                
                // Set user in auth storage and process login
                $authStorage = $subject->getAuthStorage();
                if ($authStorage) {
                    $authStorage->setUser($credentialStorage);
                    $authStorage->processLogin();
                    
                    // Dispatch login success event
                    $this->eventManager->dispatch(
                        'backend_auth_user_login_success',
                        ['user' => $credentialStorage]
                    );
                }
            } else {
                // Fallback: try to set user directly in auth storage
                $authStorage = $subject->getAuthStorage();
                if ($authStorage) {
                    // Create a simple user object with the admin data
                    $userData = [
                        'user_id' => $user->getId(),
                        'username' => $user->getUsername(),
                        'email' => $user->getEmail(),
                        'firstname' => $user->getFirstname(),
                        'lastname' => $user->getLastname(),
                        'is_active' => $user->getIsActive()
                    ];
                    
                    // Set user data directly in auth storage
                    $authStorage->setUserData($userData);
                    $authStorage->processLogin();
                    
                    // Dispatch login success event
                    $this->eventManager->dispatch(
                        'backend_auth_user_login_success',
                        ['user' => $user]
                    );
                }
            }
        } catch (\Exception $e) {
            error_log('FreeAdmin: Error setting credential storage and login: ' . $e->getMessage());
        }
    }
}
