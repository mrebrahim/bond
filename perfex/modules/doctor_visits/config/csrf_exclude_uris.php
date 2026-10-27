<?php

defined('BASEPATH') or exit('No direct script access allowed');

// الـ API بتاع n8n محمي بالـ X-DV-Token مش بالـ CSRF
return ['doctor_visits/dv_api/.+'];
