<?php
/**
 * Proxima Admin Panel - Translations
 * 
 * Multi-language support for admin panel
 */

// All translations
$TRANSLATIONS = [
    'tr' => [
        // General
        'admin_panel' => 'Yönetim Paneli',
        'logout' => 'Çıkış Yap',
        'back' => 'Geri',
        'save' => 'Kaydet',
        'cancel' => 'İptal',
        'delete' => 'Sil',
        'edit' => 'Düzenle',
        'view' => 'Görüntüle',
        'create' => 'Oluştur',
        'actions' => 'İşlemler',
        'yes' => 'Evet',
        'no' => 'Hayır',
        'search' => 'Ara',
        'filter' => 'Filtrele',
        'close' => 'Kapat',
        'confirm' => 'Onayla',
        
        // Login
        'login' => 'Giriş Yap',
        'admin_login' => 'Yönetici Girişi',
        'username' => 'Kullanıcı Adı',
        'password' => 'Şifre',
        'sign_in' => 'Giriş Yap →',
        'invalid_credentials' => 'Geçersiz kullanıcı adı veya şifre',
        
        // Dashboard
        'database_management' => 'Veritabanı Yönetimi',
        'total_models' => 'Toplam Model',
        'synced' => 'Senkronize',
        'pending_changes' => 'Bekleyen Değişiklikler',
        'orphaned_tables' => 'Sahipsiz Tablolar',
        'all_models' => 'Tüm Modeller',
        'model' => 'Model',
        'table_name' => 'Tablo Adı',
        'status' => 'Durum',
        'sync_all' => 'Tümünü Senkronize Et',
        'fresh_migration' => 'Temiz Migrasyon',
        'sync' => 'Senkronize Et',
        'synced_badge' => '✓ Senkronize',
        'pending_badge' => '! Beklemede',
        'orphaned_badge' => '✗ Sahipsiz',
        'data_loss_warning' => '⚠ Veri Kaybı',
        
        // Empty states
        'no_models_found' => 'Model Bulunamadı',
        'create_models_hint' => 'models/ dizinine model dosyaları oluşturun',
        'no_records_found' => 'Kayıt Bulunamadı',
        'no_records_hint' => 'İlk kaydı oluşturmak için "Yeni Kayıt" butonuna tıklayın',
        
        // Model view
        'new_record' => 'Yeni Kayıt',
        'records' => 'Kayıt',
        'showing_records' => 'kayıt gösteriliyor',
        'id' => 'ID',
        'create_new' => '✚ Yeni Oluştur',
        'record_count' => 'kayıt',
        'record_count_plural' => 'kayıt',
        'matching' => 'eşleşen',
        'all_columns' => 'Tüm Sütunlar',
        'clear' => '✕ Temizle',
        'no_records_match' => 'Aramanızla eşleşen kayıt bulunamadı',
        'table_empty' => 'Bu tablo boş.',
        'create_new_record' => 'Yeni kayıt oluştur',
        'view_details' => 'Detayları Görüntüle',
        'previous' => '← Önceki',
        'next' => 'Sonraki →',
        'page_of' => 'Sayfa',
        'of' => '/',
        
        // Record view
        'record_details' => 'Kayıt Detayları',
        'edit_record' => 'Kaydı Düzenle',
        'delete_record' => 'Kaydı Sil',
        'field' => 'Alan',
        'value' => 'Değer',
        
        // Forms
        'create_record' => 'Kayıt Oluştur',
        'update_record' => 'Kayıt Güncelle',
        'required_field' => 'Zorunlu Alan',
        'new' => 'Yeni',
        'select' => '-- Seçiniz --',
        'true' => 'Doğru',
        'false' => 'Yanlış',
        'enter' => 'Giriniz',
        'maximum' => 'Maksimum',
        'format' => 'Format',
        'maximum_length' => 'Maksimum uzunluk',
        'default' => 'Varsayılan',
        'create' => 'Oluştur',
        'update' => 'Güncelle',
        'back_to_list' => '← Listeye Dön',
        
        // Messages
        'model_synced_success' => 'Model başarıyla senkronize edildi!',
        'all_models_synced_success' => 'Tüm modeller başarıyla senkronize edildi!',
        'fresh_migration_success' => 'Temiz migrasyon tamamlandı! Tüm tablolar yeniden oluşturuldu.',
        'table_deleted_success' => 'Tablo başarıyla silindi!',
        'record_created_success' => 'Kayıt başarıyla oluşturuldu!',
        'record_updated_success' => 'Kayıt başarıyla güncellendi!',
        'record_deleted_success' => 'Kayıt başarıyla silindi!',
        'update_completed_success' => 'Güncelleme başarıyla tamamlandı!',
        'files_updated' => 'dosya güncellendi',
        
        // Confirmations
        'confirm_sync_model' => 'Model senkronize edilsin mi',
        'confirm_sync_all' => 'Tüm modeller senkronize edilsin mi?',
        'confirm_fresh_migration' => 'TEHLİKE: Bu işlem TÜM tablolarınızı silecek ve yeniden oluşturacaktır.\nTÜM VERİLERİNİZ KALICI OLARAK SİLİNECEKTİR!\n\nDevam etmek istediğinizden emin misiniz?',
        'confirm_delete_table' => 'Tablo silinsin mi? Bu işlem geri alınamaz!',
        'confirm_delete_record' => 'Bu kayıt silinsin mi? Bu işlem geri alınamaz!',
        
        // Errors
        'error_occurred' => 'Bir hata oluştu',
        'no_action_specified' => 'İşlem belirtilmedi',
        'invalid_model_class' => 'Geçersiz model sınıfı',
        'table_name_required' => 'Tablo adı gerekli',
        'record_id_required' => 'Kayıt ID gerekli',
        'invalid_security_token' => 'Geçersiz güvenlik jetonu. Lütfen tekrar deneyin.',
        
        // Update
        'update_available' => '⬆ Güncelleme Mevcut',
        'version' => 'v',
        
        // Sidebar
        'models' => 'Modeller',
        'administrator' => 'Yönetici',
    ],
    
    'en' => [
        // General
        'admin_panel' => 'Admin Panel',
        'logout' => 'Logout',
        'back' => 'Back',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'view' => 'View',
        'create' => 'Create',
        'actions' => 'Actions',
        'yes' => 'Yes',
        'no' => 'No',
        'search' => 'Search',
        'filter' => 'Filter',
        'close' => 'Close',
        'confirm' => 'Confirm',
        
        // Login
        'login' => 'Login',
        'admin_login' => 'Admin Login',
        'username' => 'Username',
        'password' => 'Password',
        'sign_in' => 'Sign In →',
        'invalid_credentials' => 'Invalid username or password',
        
        // Dashboard
        'database_management' => 'Database Management',
        'total_models' => 'Total Models',
        'synced' => 'Synced',
        'pending_changes' => 'Pending Changes',
        'orphaned_tables' => 'Orphaned Tables',
        'all_models' => 'All Models',
        'model' => 'Model',
        'table_name' => 'Table Name',
        'status' => 'Status',
        'sync_all' => 'Sync All',
        'fresh_migration' => 'Fresh Migration',
        'sync' => 'Sync',
        'synced_badge' => '✓ Synced',
        'pending_badge' => '! Pending',
        'orphaned_badge' => '✗ Orphaned',
        'data_loss_warning' => '⚠ Data Loss',
        
        // Empty states
        'no_models_found' => 'No Models Found',
        'create_models_hint' => 'Create model files in the models/ directory',
        'no_records_found' => 'No Records Found',
        'no_records_hint' => 'Click "New Record" to create the first record',
        
        // Model view
        'new_record' => 'New Record',
        'records' => 'Records',
        'showing_records' => 'records found',
        'id' => 'ID',
        'create_new' => '✚ Create New',
        'record_count' => 'record',
        'record_count_plural' => 'records',
        'matching' => 'matching',
        'all_columns' => 'All Columns',
        'clear' => '✕ Clear',
        'no_records_match' => 'No records match your search criteria',
        'table_empty' => 'This table is empty.',
        'create_new_record' => 'Create a new record',
        'view_details' => 'View Details',
        'previous' => '← Previous',
        'next' => 'Next →',
        'page_of' => 'Page',
        'of' => 'of',
        
        // Record view
        'record_details' => 'Record Details',
        'edit_record' => 'Edit Record',
        'delete_record' => 'Delete Record',
        'field' => 'Field',
        'value' => 'Value',
        
        // Forms
        'create_record' => 'Create Record',
        'update_record' => 'Update Record',
        'required_field' => 'Required Field',
        'new' => 'New',
        'select' => '-- Select --',
        'true' => 'True',
        'false' => 'False',
        'enter' => 'Enter',
        'maximum' => 'Maximum',
        'format' => 'Format',
        'maximum_length' => 'Maximum length',
        'default' => 'Default',
        'create' => 'Create',
        'update' => 'Update',
        'back_to_list' => '← Back to List',
        
        // Messages
        'model_synced_success' => 'Model synced successfully!',
        'all_models_synced_success' => 'All models synced successfully!',
        'fresh_migration_success' => 'Fresh migration completed! All tables recreated.',
        'table_deleted_success' => 'Table deleted successfully!',
        'record_created_success' => 'Record created successfully!',
        'record_updated_success' => 'Record updated successfully!',
        'record_deleted_success' => 'Record deleted successfully!',
        'update_completed_success' => 'Update completed successfully!',
        'files_updated' => 'files updated',
        
        // Confirmations
        'confirm_sync_model' => 'Sync model',
        'confirm_sync_all' => 'Sync all models?',
        'confirm_fresh_migration' => 'DANGER: This will DROP ALL your tables and recreate them.\nALL DATA WILL BE PERMANENTLY LOST!\n\nAre you sure you want to continue?',
        'confirm_delete_table' => 'Delete table? This action cannot be undone!',
        'confirm_delete_record' => 'Delete this record? This action cannot be undone!',
        
        // Errors
        'error_occurred' => 'An error occurred',
        'no_action_specified' => 'No action specified',
        'invalid_model_class' => 'Invalid model class',
        'table_name_required' => 'Table name is required',
        'record_id_required' => 'Record ID is required',
        'invalid_security_token' => 'Invalid security token. Please try again.',
        
        // Update
        'update_available' => '⬆ Update Available',
        'version' => 'v',
        
        // Sidebar
        'models' => 'Models',
        'administrator' => 'Administrator',
    ],
];

/**
 * Get translation for a key in current language
 */
function t(string $key, array $params = []): string
{
    global $TRANSLATIONS;
    
    $lang = getCurrentLanguage();
    
    // Get translation
    $text = $TRANSLATIONS[$lang][$key] ?? $TRANSLATIONS['en'][$key] ?? $key;
    
    // Replace parameters
    foreach ($params as $param => $value) {
        $text = str_replace('{' . $param . '}', $value, $text);
    }
    
    return $text;
}

/**
 * Get current language from session or default
 */
function getCurrentLanguage(): string
{
    if (!isset($_SESSION['admin_language'])) {
        $_SESSION['admin_language'] = 'en'; // Default to English
    }
    return $_SESSION['admin_language'];
}

/**
 * Set language in session
 */
function setLanguage(string $lang): void
{
    if (in_array($lang, ['tr', 'en'])) {
        $_SESSION['admin_language'] = $lang;
    }
}

/**
 * Get available languages
 */
function getAvailableLanguages(): array
{
    return [
        'en' => 'English',
        'tr' => 'Türkçe',
    ];
}
