// Leaflet Map instance
let map = null;
let currentMarkers = [];
let userSkillsList = []; // To store skills fetched from setup_db.php for the dropdown
let currentUserId = null; // To store the logged-in user's ID

document.addEventListener('DOMContentLoaded', () => {
    // DOM elements (Obter referências apenas para elementos que são sempre esperados no DOM raiz)
    const authSection = document.getElementById('auth-section');
    const appSection = document.getElementById('app-section');
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const loginMessage = document.getElementById('login-message');
    const registerMessage = document.getElementById('register-message');
    const welcomeMessage = document.getElementById('welcome-message');
    const logoutButton = document.getElementById('logout-button');

    const profileTab = document.getElementById('profile-tab');
    const mapTab = document.getElementById('map-tab');
    const requestsTab = document.getElementById('requests-tab');
    const profileForm = document.getElementById('profile-form');
    const mapSection = document.getElementById('map-section');
    const requestsSection = document.getElementById('requests-section');

    // Requests lists (declarar aqui pois são sempre esperadas)
    const receivedRequestsList = document.getElementById('received-requests-list');
    const sentRequestsList = document.getElementById('sent-requests-list');
    const noReceivedRequests = document.getElementById('no-received-requests');
    const noSentRequests = document.getElementById('no-sent-requests');
    const requestsMessage = document.getElementById('requests-message');


    // --- Tab Switching Logic ---
    const activateTab = (activeTabButton, activeSection) => {
        // Deactivate all main tabs
        [profileTab, mapTab, requestsTab].forEach(btn => btn.classList.remove('active'));
        [profileForm, mapSection, requestsSection].forEach(sec => sec.classList.add('hidden'));

        // Activate selected tab
        activeTabButton.classList.add('active');
        activeSection.classList.remove('hidden');
    };

    loginTab.addEventListener('click', () => {
        loginTab.classList.add('active');
        registerTab.classList.remove('active');
        loginForm.classList.remove('hidden');
        registerForm.classList.add('hidden');
        loginMessage.textContent = '';
        registerMessage.textContent = '';
    });

    registerTab.addEventListener('click', () => {
        registerTab.classList.add('active');
        loginTab.classList.remove('active');
        registerForm.classList.remove('hidden');
        loginForm.classList.add('hidden');
        loginMessage.textContent = '';
        registerMessage.textContent = '';
    });

    profileTab.addEventListener('click', async () => {
        activateTab(profileTab, profileForm);
        await loadUserProfile(); // Load profile data when tab is clicked
    });

    mapTab.addEventListener('click', () => {
        activateTab(mapTab, mapSection);
        initMap(); // Initialize or refresh map when tab is clicked
        fetchAndDisplayTalents();
    });

    requestsTab.addEventListener('click', () => {
        activateTab(requestsTab, requestsSection);
        fetchAndDisplayRequests(); // Load requests when tab is clicked
    });

    // --- Authentication / App Flow ---
    const checkAuth = async () => {
        try {
            const response = await fetch('login.php?check_auth=true');
            const data = await response.json();
            if (data.loggedIn) {
                currentUserId = data.userId; // Store user ID
                authSection.classList.add('hidden');
                appSection.classList.remove('hidden');
                welcomeMessage.textContent = `Bem-vindo(a), ${data.username}!`;
                await fetchSkills(); // Load skills dropdown FIRST
                profileTab.click(); // Default to profile tab, which also loads user profile
            } else {
                authSection.classList.remove('hidden');
                appSection.classList.add('hidden');
            }
        } catch (error) {
            console.error('Erro na verificação de autenticação:', error);
            // Fallback UI or message for critical error
            authSection.classList.remove('hidden');
            appSection.classList.add('hidden');
            if (loginMessage) {
                loginMessage.textContent = 'Erro ao conectar ao servidor. Tente novamente.';
                loginMessage.style.color = 'red';
            }
        }
    };

    const fetchSkills = async () => {
        try {
            const profileSkills = document.getElementById('profile-skills'); // Obter aqui
            if (!profileSkills) {
                console.warn("Elemento 'profile-skills' não encontrado no DOM. Não foi possível carregar as habilidades.");
                return;
            }

            const response = await fetch('get_skills.php');
            if (!response.ok) { // Check if HTTP response is OK (200-299)
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            const data = await response.json(); // Parse JSON only if response is OK
            
            if (data.success && data.skills) {
                userSkillsList = data.skills;
                profileSkills.innerHTML = ''; // Clear existing options
                data.skills.forEach(skill => {
                    const option = document.createElement('option');
                    option.value = skill.id;
                    option.textContent = skill.name;
                    profileSkills.appendChild(option);
                });
                console.log('Habilidades carregadas com sucesso:', userSkillsList); // Debugging
            } else {
                console.error('Erro ao buscar habilidades:', data.message || 'Dados inválidos recebidos.');
                const profileMessage = document.getElementById('profile-message'); // Obter aqui
                if(profileMessage) {
                    profileMessage.textContent = 'Erro ao carregar lista de habilidades. Tente recarregar a página.';
                    profileMessage.style.color = 'red';
                }
            }
        } catch (error) {
            console.error('Erro de rede ou JSON ao buscar habilidades:', error);
            const profileMessage = document.getElementById('profile-message'); // Obter aqui
            if(profileMessage) {
                profileMessage.textContent = `Erro de rede ou formato de dados inválido ao carregar habilidades. Verifique o console. Detalhes: ${error.message || error}`;
                profileMessage.style.color = 'red';
            }
        }
    };

    const loadUserProfile = async () => {
        try {
            // Obter referências dos elementos do formulário de perfil aqui, para garantir que existem
            const profileCity = document.getElementById('profile-city');
            const profileState = document.getElementById('profile-state');
            const profileLocationText = document.getElementById('profile-location-text');
            const profileSkills = document.getElementById('profile-skills');
            const profileAvailabilityHours = document.getElementById('profile-availability-hours');
            const profileAvailabilityPeriod = document.getElementById('profile-availability-period');
            const profileSeekingText = document.getElementById('profile-seeking-text');
            const profileContactEmail = document.getElementById('profile-contact-email');
            const profileContactPhone = document.getElementById('profile-contact-phone');
            const profileMessage = document.getElementById('profile-message');


            const response = await fetch('profile_update.php?get_profile=true');
            if (!response.ok) { // Check for HTTP errors
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            const data = await response.json();
            if (data.success && data.user) {
                // Populate simplified address fields (verificar existência antes de atribuir)
                if (profileCity) profileCity.value = data.user.city || '';
                if (profileState) profileState.value = data.user.state || '';
                
                // Safely access profileLocationText
                if (profileLocationText) { 
                    profileLocationText.value = data.user.location_text || '';
                    profileLocationText.dataset.latitude = data.user.latitude || '';
                    profileLocationText.dataset.longitude = data.user.longitude || '';
                } else {
                    console.warn("Elemento 'profile-location-text' não encontrado no DOM. O valor de location_text e coordenadas não será exibido.");
                }

                if (profileAvailabilityHours) profileAvailabilityHours.value = data.user.availability_hours || '';
                if (profileAvailabilityPeriod) profileAvailabilityPeriod.value = data.user.availability_period || 'semana';
                if (profileSeekingText) profileSeekingText.value = data.user.seeking_text || '';
                if (profileContactEmail) profileContactEmail.value = data.user.contact_email || '';
                if (profileContactPhone) profileContactPhone.value = data.user.contact_phone || ''; // Corrigido para .value


                // Select user's skills in the multi-select dropdown
                if (profileSkills) {
                    const selectedSkillIds = data.user.skills ? data.user.skills.map(s => s.skill_id.toString()) : [];
                    Array.from(profileSkills.options).forEach(option => {
                        option.selected = selectedSkillIds.includes(option.value);
                    });
                } else {
                     console.warn("Elemento 'profile-skills' não encontrado no DOM.");
                }
                
                if (profileMessage) profileMessage.textContent = ''; // Clear message on successful load

            } else {
                if (profileMessage) {
                    profileMessage.textContent = 'Erro ao carregar perfil. Por favor, preencha seus dados.';
                    profileMessage.style.color = 'red';
                }
            }
        } catch (error) {
            if (profileMessage) {
                profileMessage.textContent = `Erro ao carregar perfil. Verifique o console. Detalhes: ${error.message || error}`;
                profileMessage.style.color = 'red';
            }
            console.error('Erro ao carregar perfil:', error);
        }
    };

    // --- Geolocation ---
    // Obter referência aqui para usar no listener
    const getLocationBtn = document.getElementById('get-location-btn'); 
    if (getLocationBtn) { 
        getLocationBtn.addEventListener('click', () => {
            // Obter referências dos elementos aqui dentro do listener
            const profileMessage = document.getElementById('profile-message'); 
            const profileLocationText = document.getElementById('profile-location-text');
            const profileCity = document.getElementById('profile-city');
            const profileState = document.getElementById('profile-state');

            if (navigator.geolocation) {
                if (profileMessage) {
                    profileMessage.textContent = 'Buscando localização...';
                    profileMessage.style.color = 'gray';
                }
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    
                    // Use OpenStreetMap Nominatim for reverse geocoding to get human-readable location text
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`)
                        .then(response => response.json())
                        .then(geoData => {
                            let locationDisplayName = geoData.display_name || `Lat: ${lat.toFixed(4)}, Lon: ${lon.toFixed(4)}`;
                            let cityFromGeo = geoData.address.city || geoData.address.town || geoData.address.village || '';
                            let stateFromGeo = geoData.address.state_code || geoData.address.state || '';

                            // Safely access profileLocationText
                            if (profileLocationText) { 
                                profileLocationText.value = locationDisplayName;
                                profileLocationText.dataset.latitude = lat;
                                profileLocationText.dataset.longitude = lon;
                            } else {
                                console.warn("Elemento 'profile-location-text' não encontrado no DOM. Coordenadas não serão armazenadas.");
                            }

                            if (profileCity) profileCity.value = cityFromGeo;
                            if (profileState) profileState.value = stateFromGeo.split('-')[1] || stateFromGeo; // Tries to get UF if in "BR-SP" format

                            if (profileMessage) {
                                profileMessage.textContent = 'Localização obtida!';
                                profileMessage.style.color = 'green';
                            }
                        })
                        .catch(error => {
                            console.error('Erro no reverse geocoding:', error);
                            // Safely access profileLocationText for fallback
                            if (profileLocationText) { 
                                profileLocationText.value = `Coordenadas: ${lat.toFixed(4)}, ${lon.toFixed(4)}`; // Fallback
                                profileLocationText.dataset.latitude = lat;
                                profileLocationText.dataset.longitude = lon;
                            }
                            if (profileMessage) {
                                profileMessage.textContent = 'Localização obtida, mas não foi possível preencher o endereço completo.';
                                profileMessage.style.color = 'orange';
                            }
                        });

                }, (error) => {
                    console.error('Erro ao obter localização:', error);
                    if (profileMessage) {
                        profileMessage.textContent = 'Não foi possível obter sua localização. Por favor, preencha Cidade/Estado.';
                        profileMessage.style.color = 'red';
                    }
                }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }); // Increased timeout
            } else {
                if (profileMessage) {
                    profileMessage.textContent = 'Geolocalização não suportada pelo seu navegador.';
                    profileMessage.style.color = 'orange';
                }
            }
        });
    }

    // --- Forms Submission ---
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = e.target['login-username'].value;
        const password = e.target['login-password'].value;

        try {
            const response = await fetch('login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await response.json();
            loginMessage.textContent = data.message;
            loginMessage.style.color = data.success ? 'green' : 'red';
            if (data.success) {
                checkAuth(); // Re-check auth to update UI
            }
        } catch (error) {
            loginMessage.textContent = 'Erro de rede ao fazer login. Tente novamente.';
            loginMessage.style.color = 'red';
            console.error('Erro de rede no login:', error);
        }
    });

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = e.target['register-username'].value;
        const email = e.target['register-email'].value;
        const password = e.target['register-password'].value;
        
        const registerCity = document.getElementById('register-city'); // Obter aqui
        const registerState = document.getElementById('register-state'); // Obter aqui
        const city = registerCity ? registerCity.value : null; // Acessar com segurança
        const state = registerState ? registerState.value : null; // Acessar com segurança

        try {
            const response = await fetch('register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    username, email, password,
                    city, state
                })
            });
            const data = await response.json();
            registerMessage.textContent = data.message;
            registerMessage.style.color = data.success ? 'green' : 'red';
            if (data.success) {
                loginTab.click(); // Switch to login tab after successful registration
                e.target.reset(); // Clear form
            }
        }
        catch (error) {
            registerMessage.textContent = 'Erro de rede ao registrar. Tente novamente.';
            registerMessage.style.color = 'red';
            console.error('Erro de rede no registro:', error);
        }
    });

    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const profileCity = document.getElementById('profile-city'); // Obter aqui
        const profileState = document.getElementById('profile-state'); // Obter aqui
        const profileLocationText = document.getElementById('profile-location-text'); // Obter aqui
        const profileSkills = document.getElementById('profile-skills'); // Obter aqui
        const profileAvailabilityHours = document.getElementById('profile-availability-hours'); // Obter aqui
        const profileAvailabilityPeriod = document.getElementById('profile-availability-period'); // Obter aqui
        const profileSeekingText = document.getElementById('profile-seeking-text'); // Obter aqui
        const profileContactEmail = document.getElementById('profile-contact-email'); // Obter aqui
        const profileContactPhone = document.getElementById('profile-contact-phone'); // Obter aqui
        const profileMessage = document.getElementById('profile-message'); // Obter aqui

        const city = profileCity ? profileCity.value : null;
        const state = profileState ? profileState.value : null;
        const locationText = profileLocationText ? profileLocationText.value : null; 
        const latitude = profileLocationText ? (profileLocationText.dataset.latitude || null) : null;
        const longitude = profileLocationText ? (profileLocationText.dataset.longitude || null) : null;

        const selectedSkills = profileSkills ? Array.from(profileSkills.selectedOptions).map(option => option.value) : [];
        const availabilityHours = profileAvailabilityHours ? profileAvailabilityHours.value : null;
        const availabilityPeriod = profileAvailabilityPeriod ? profileAvailabilityPeriod.value : null;
        const seekingText = profileSeekingText ? profileSeekingText.value : null;
        const contactEmail = profileContactEmail ? profileContactEmail.value : null;
        const contactPhone = profileContactPhone ? profileContactPhone.value : null; // CORREÇÃO FINAL: de .contactPhone para .value

        try {
            const response = await fetch('profile_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    city, state,
                    location_text: locationText,
                    latitude: latitude,
                    longitude: longitude,
                    skills: selectedSkills,
                    availability_hours: availabilityHours,
                    availability_period: availabilityPeriod,
                    seeking_text: seekingText,
                    contact_email: contactEmail,
                    contact_phone: contactPhone
                })
            });
            const data = await response.json();
            if (profileMessage) {
                profileMessage.textContent = data.message;
                profileMessage.style.color = data.success ? 'green' : 'red';
            }
            if (data.success) {
                await loadUserProfile(); // Re-load profile to ensure UI reflects changes
                mapTab.click(); // Mudar para a aba do mapa após salvar o perfil
            }
        } catch (error) {
            if (profileMessage) {
                profileMessage.textContent = 'Erro de rede ao atualizar perfil. Tente novamente.';
                profileMessage.style.color = 'red';
            }
            console.error('Erro de rede na atualização de perfil:', error);
        }
    });

    logoutButton.addEventListener('click', async () => {
        try {
            const response = await fetch('logout.php');
            const data = await response.json();
            if (data.success) {
                checkAuth(); // Go back to login screen
                welcomeMessage.textContent = 'Bem-vindo(a)!'; // Reset welcome message
                if (map) { // Clean up map and markers
                    map.remove();
                    map = null;
                }
                currentMarkers = [];
            }
        } catch (error) {
            alert('Erro ao fazer logout. Tente novamente.');
            console.error('Erro de rede no logout:', error);
        }
    });

    // --- Map Logic ---
    const initMap = () => {
        if (!map) {
            map = L.map('map').setView([-23.55052, -46.633309], 12); // Ex: São Paulo coordinates

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
        } else {
            map.invalidateSize(); // Invalidate size if map container changes visibility
        }
        currentMarkers.forEach(marker => map.removeLayer(marker)); // Clear existing markers
        currentMarkers = [];
    };

    const fetchAndDisplayTalents = async () => {
        try {
            const response = await fetch('get_talents.php');
            if (!response.ok) { // Check for HTTP errors
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            const data = await response.json();

            if (data.success && data.talents) {
                data.talents.forEach(talent => {
                    // Exclude the current user from the map
                    if (talent.id == currentUserId) {
                        return;
                    }

                    if (talent.latitude && talent.longitude) {
                        const skillsHtml = talent.skills && talent.skills.length > 0
                            ? `<ul>${talent.skills.map(s => `<li>${s.name}</li>`).join('')}</ul>`
                            : '<p>Nenhuma habilidade listada.</p>';

                        const availability = talent.availability_hours && talent.availability_period
                            ? `<p>Disponibilidade: ${talent.availability_hours}h por ${talent.availability_period}</p>`
                            : '';
                        
                        const seeking = talent.seeking_text ? `<p><strong>Busca:</strong> ${talent.seeking_text}</p>` : '';


                        const popupContent = `
                            <h3>${talent.username}</h3>
                            <p><strong>Localização:</strong> ${talent.location_text || talent.city || 'Não informada'}</p>
                            <p><strong>Habilidades Oferecidas:</strong></p>
                            ${skillsHtml}
                            ${availability}
                            ${seeking}
                            <button class="send-request-btn mt-2 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md text-sm" data-provider-id="${talent.id}">Solicitar Troca</button>
                        `;

                        const marker = L.marker([talent.latitude, talent.longitude])
                            .addTo(map)
                            .bindPopup(popupContent);
                        currentMarkers.push(marker);
                    }
                });
                map.on('popupopen', function(e) {
                    const btn = e.popup.getElement().querySelector('.send-request-btn');
                    if (btn) {
                        btn.addEventListener('click', handleSendRequest);
                    }
                });
            } else {
                console.error('Erro ao buscar talentos:', data.message || 'Dados inválidos recebidos.');
                alert('Erro ao carregar talentos no mapa. Tente novamente.');
            }
        } catch (error) {
            console.error('Erro de rede ou JSON ao buscar talentos:', error);
            alert(`Erro de rede ou formato de dados inválido ao carregar talentos. Verifique sua conexão. Detalhes: ${error.message || error}`);
        }
    };

    const handleSendRequest = async (e) => {
        const providerId = e.target.dataset.providerId;
        if (!providerId || !currentUserId || currentUserId == providerId) {
            alert('Não é possível enviar solicitação para si mesmo ou sem ID válido.');
            return;
        }

        // Simplified skill selection for popup: just take the first skill from the provider's popup list
        let requestedSkillId = null;
        let requestedSkillName = "Habilidade não especificada"; 
        const providerPopupSkillsList = e.target.closest('.leaflet-popup-content').querySelector('ul');
        if (providerPopupSkillsList && providerPopupSkillsList.children.length > 0) {
            const firstSkillName = providerPopupSkillsList.children[0].textContent;
            const skillObject = userSkillsList.find(s => s.name === firstSkillName);
            if(skillObject) {
                requestedSkillId = skillObject.id;
                requestedSkillName = skillObject.name;
            }
        }

        const message = prompt(`Envie uma mensagem para ${e.target.closest('.leaflet-popup-content').querySelector('h3').textContent} sobre a troca de habilidades (ex: "Busco ajuda com ${requestedSkillName}"):`);
        if (message === null || message.trim() === '') {
            alert('Mensagem é obrigatória para enviar a solicitação.');
            return;
        }

        try {
            const response = await fetch('send_exchange_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    provider_id: providerId,
                    requested_skill_id: requestedSkillId,
                    message: message
                })
            });
            const data = await response.json();
            alert(data.message);
            if (data.success) {
                requestsTab.click(); // Go to requests tab to see the sent request
            }
        } catch (error) {
            console.error('Erro ao enviar solicitação:', error);
            alert('Erro ao enviar solicitação. Tente novamente.');
        }
    };

    // --- Requests Logic ---
    const fetchAndDisplayRequests = async () => {
        requestsMessage.textContent = 'Carregando solicitações...';
        requestsMessage.style.color = 'gray';
        receivedRequestsList.innerHTML = '<p class="text-gray-500 text-sm" id="no-received-requests">Nenhuma solicitação recebida no momento.</p>';
        sentRequestsList.innerHTML = '<p class="text-gray-500 text-sm" id="no-sent-requests">Nenhuma solicitação enviada no momento.</p>';

        try {
            const response = await fetch('get_requests.php');
            if (!response.ok) { // Check for HTTP errors
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }
            const data = await response.json();

            if (data.success && data.requests) {
                let hasReceived = false;
                let hasSent = false;

                receivedRequestsList.innerHTML = ''; // Clear initial placeholder
                sentRequestsList.innerHTML = ''; // Clear initial placeholder


                data.requests.forEach(req => {
                    const isReceived = req.provider_id == currentUserId;
                    const listElement = document.createElement('div');
                    listElement.className = `request-card mb-3 p-4 rounded-lg shadow-sm border border-gray-200 ${req.status === 'pending' ? 'pending' : (req.status === 'accepted' ? 'accepted' : 'rejected')}`;

                    const statusClass = req.status === 'pending' ? 'pending-status' : (req.status === 'accepted' ? 'accepted-status' : 'rejected-status');
                    const statusText = req.status === 'pending' ? 'PENDENTE' : (req.status === 'accepted' ? 'ACEITA' : (req.status === 'rejected' ? 'RECUSADA' : 'CANCELADA')); 

                    // Safely get contact info
                    const requesterEmail = req.requester_contact_email || 'Não informado';
                    const requesterPhone = req.requester_contact_phone || 'Não informado';
                    const providerEmail = req.provider_contact_email || 'Não informado';
                    const providerPhone = req.provider_contact_phone || 'Não informado';

                    listElement.innerHTML = `
                        <p class="font-bold text-lg">${isReceived ? req.requester_username : req.provider_username}</p>
                        <p class="text-sm text-gray-700 mt-1">Habilidade Solicitada: ${req.skill_name || 'Não especificada'}</p>
                        <p class="text-sm text-gray-700 italic">"${req.message}"</p>
                        <span class="request-status ${statusClass}">${statusText}</span>
                        ${req.status === 'accepted' ? `
                            <div class="mt-3 text-sm text-gray-800">
                                <p><strong>Email:</strong> ${isReceived ? requesterEmail : providerEmail}</p>
                                <p><strong>Telefone:</strong> ${isReceived ? requesterPhone : providerPhone}</p>
                            </div>
                        ` : ''}
                        <div class="request-actions mt-3 flex">
                            ${isReceived && req.status === 'pending' ? `
                                <button class="accept-btn" data-request-id="${req.id}" data-action="accept">Aceitar</button>
                                <button class="reject-btn" data-request-id="${req.id}" data-action="reject">Recusar</button>
                            ` : ''}
                            ${req.status === 'accepted' ? `
                                <button class="contact-btn" onclick="alert('Email: ${isReceived ? requesterEmail : providerEmail}\\nTelefone: ${isReceived ? requesterPhone : providerPhone}')">Ver Contato</button>
                            ` : ''}
                        </div>
                    `;

                    if (isReceived) {
                        receivedRequestsList.appendChild(listElement);
                        hasReceived = true;
                    } else {
                        sentRequestsList.appendChild(listElement);
                        hasSent = true;
                    }
                });

                if (!hasReceived) noReceivedRequests.classList.remove('hidden'); else noReceivedRequests.classList.add('hidden');
                if (!hasSent) noSentRequests.classList.remove('hidden'); else noSentRequests.classList.add('hidden');

                // Attach event listeners for accept/reject buttons
                receivedRequestsList.querySelectorAll('.accept-btn').forEach(btn => btn.addEventListener('click', handleRequestStatusUpdate));
                receivedRequestsList.querySelectorAll('.reject-btn').forEach(btn => btn.addEventListener('click', handleRequestStatusUpdate));

                requestsMessage.textContent = ''; // Clear loading message
            } else {
                requestsMessage.textContent = data.message || 'Erro ao carregar solicitações.';
                requestsMessage.style.color = 'red';
                console.error('Dados inválidos recebidos ao carregar solicitações:', data); // Debugging
            }
        } catch (error) {
            requestsMessage.textContent = 'Erro de rede ao carregar solicitações. Verifique sua conexão ou logs do servidor.';
            requestsMessage.style.color = 'red';
            console.error('Erro de rede ou JSON ao carregar solicitações:', error);
        }
    };

    const handleRequestStatusUpdate = async (e) => {
        const requestId = e.target.dataset.requestId;
        const action = e.target.dataset.action; // 'accept' or 'reject'

        try {
            const response = await fetch('update_request_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ request_id: requestId, status: action })
            });
            const data = await response.json();
            alert(data.message);
            if (data.success) {
                fetchAndDisplayRequests(); // Refresh the list
            }
        } catch (error) {
            console.error('Erro ao atualizar status da solicitação:', error);
            alert('Erro ao atualizar status da solicitação. Tente novamente.');
        }
    };

    // Initial check on page load
    checkAuth();
});
