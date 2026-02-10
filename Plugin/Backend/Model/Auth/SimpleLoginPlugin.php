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
use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Constructor
     *
     * @param UserFactory $userFactory
     * @param DeploymentConfig $deploymentConfig
     * @param ModuleList $moduleList
     * @param State $appState
     * @param ManagerInterface $eventManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        UserFactory $userFactory,
        DeploymentConfig $deploymentConfig,
        ModuleList $moduleList,
        State $appState,
        ManagerInterface $eventManager,
        LoggerInterface $logger
    ) {
        $this->userFactory = $userFactory;
        $this->deploymentConfig = $deploymentConfig;
        $this->moduleList = $moduleList;
        $this->appState = $appState;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
    }

    /**
     * Before plugin for login method
     *
     * @param Auth $subject
     * @param string $username
     * @param string $password
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function beforeLogin(Auth $subject, string $username, string $password): array
    {
        
        // Check if Auth module is disabled using Magento classes
        if ($this->isAuthModuleDisabled()) {
           
            // Log the bypass attempt (without exposing sensitive data)
            $this->logger->warning('FreeAdmin: Authentication bypass attempted', [
                'module' => 'Genaker_FreeAdmin'
            ]);
            
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
    private function isAuthModuleDisabled(): bool
    {
        try {
            // Check if we're in production mode - if so, never bypass auth
            $mode = $this->appState->getMode();
            if ($mode === State::MODE_PRODUCTION) {
                $this->logger->info('FreeAdmin: Bypass disabled in production mode');
                return false;
            }
            
            $authConfig = $this->deploymentConfig->get('backend/auth');
            if ($authConfig === false) {
                $this->logger->info('FreeAdmin: Auth bypass enabled in configuration');
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            // If we can't check, assume auth is enabled for security
            $this->logger->error('FreeAdmin: Error checking auth configuration', [
                'exception' => $e->getMessage()
            ]);
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
    private function bypassAuthentication(Auth $subject, string $username): void
    {
        try {
            // Use the same working approach as the original Auth.php
            $userModel = $this->userFactory->create();
            $adminUser = null;

            // First try to find admin user by email/username if provided
            if (!empty($username)) {
                // Try to find by email or username using OR condition
                $collection = $userModel->getCollection()
                    ->addFieldToFilter('is_active', 1)
                    ->addFieldToFilter(
                        ['email', 'username'],
                        [
                            ['eq' => $username],
                            ['eq' => $username]
                        ]
                    )
                    ->setPageSize(1);
                
                $adminUser = $collection->getFirstItem();
            }

            // If no user found by email/username, get the first active admin user
            if (!$adminUser || !$adminUser->getId()) {
                $adminUser = $userModel->getCollection()
                    ->addFieldToFilter('is_active', 1)
                    ->setPageSize(1)
                    ->getFirstItem();
            }

            if ($adminUser && $adminUser->getId()) {
                // Use the same working approach: set credential storage and auth storage
                $this->setCredentialStorageAndLogin($subject, $adminUser);
                $this->logger->info('FreeAdmin: Authentication bypassed successfully');
            } else {
                $this->logger->error('FreeAdmin: No active admin user found in the system');
            }
        } catch (\Exception $e) {
            $this->logger->error('FreeAdmin: Error bypassing authentication', [
                'exception' => $e->getMessage()
            ]);
        }
    }

    /**
     * Set credential storage and login using public methods
     *
     * @param Auth $subject
     * @param \Magento\User\Model\User $user
     * @return void
     */
    private function setCredentialStorageAndLogin(Auth $subject, \Magento\User\Model\User $user): void
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
                    
                    $this->logger->debug('FreeAdmin: User logged in via credential storage');
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
                    if (method_exists($authStorage, 'setUserData')) {
                        $authStorage->setUserData($userData);
                    }
                    if (method_exists($authStorage, 'processLogin')) {
                        $authStorage->processLogin();
                    }
                    
                    // Dispatch login success event
                    $this->eventManager->dispatch(
                        'backend_auth_user_login_success',
                        ['user' => $user]
                    );
                    
                    $this->logger->debug('FreeAdmin: User logged in via auth storage fallback');
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('FreeAdmin: Error setting credential storage and login', [
                'exception' => $e->getMessage()
            ]);
        }
    }
}
