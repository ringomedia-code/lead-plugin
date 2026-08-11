jQuery(document).ready(function ($) {
    // RingoLeads uses one CSS class for both source and location.
    // Location 1: rl_form_request_google_ads | Location 2: rl_form_request_google_ads_2 | etc.
    // RingoLeads expects the canonical lowercase source slugs below.
    // The CRM maps each slug to its display label (for example, google_ads -> Google Ads).
    const ringoLeadsSources = {
        'facebook': 'facebook',
        'facebook_ads': 'facebook_ads',
        'google_maps': 'google_maps',
        'website': 'website',
        'google': 'google',
        'instagram': 'instagram',
        'google_ads': 'google_ads',
        'organic': 'organic',
        'calendly': 'calendly',
        'sms': 'sms',
        'ai': 'ai',
        'zapier': 'zapier'
    };

    function findRingoLeadsRoute($form) {
        let route = null;
        $form.add($form.parents()).each(function () {
            if (route) return false;
            const className = typeof this.className === 'string' ? this.className : '';
            const classes = className.split(/\s+/).filter(Boolean);
            for (const classToken of classes) {
                // Backward compatibility with the old generic RingoLeads classes.
                const legacyMatch = classToken.match(/^rl_form_request(?:_(\d+))?$/);
                if (legacyMatch) {
                    route = {
                        classToken,
                        location: legacyMatch[1] ? parseInt(legacyMatch[1], 10) : 1,
                        source: 'wordpress',
                        legacy: true
                    };
                    return false;
                }

                const match = classToken.match(/^rl_form_request_([a-z0-9_]+?)(?:_(\d+))?$/);
                if (!match) continue;

                const sourceSlug = match[1];
                if (!Object.prototype.hasOwnProperty.call(ringoLeadsSources, sourceSlug)) continue;

                route = {
                    classToken,
                    location: match[2] ? parseInt(match[2], 10) : 1,
                    source: ringoLeadsSources[sourceSlug],
                    legacy: false
                };
                return false;
            }
        });
        return route;
    }
    // Function to handle form submissions
    function handleFormSubmission(e, formType, routeInfo = null) {
        e.preventDefault(); // Prevent default form submission

        // Base class prefix for PBX / RepairDesk. RingoLeads routing is handled
        // separately because one class now carries BOTH source and location.
        const basePrefix = formType === 'rd' ? 'rd_form_request' : formType === 'rl' ? 'rl' : 'form_submit_request';
        const apiName = formType === 'rd' ? 'RepairDesk' : formType === 'rl' ? 'RingoLeads' : 'PBX';

        let formClassType = basePrefix;
        let formIdentifier = formType === 'rd' ? 'Ringo Media' : formType === 'rl' ? '' : '26';

        if (formType === 'rl') {
            routeInfo = routeInfo || findRingoLeadsRoute($(this));
            if (!routeInfo) return;
            formClassType = routeInfo.location > 1 ? `rl_source_${routeInfo.location}` : 'rl_source';
            formIdentifier = routeInfo.source;
        } else {
            // Get the closest element carrying a class that starts with the base prefix
            const container = $(this).closest(`[class*='${basePrefix}']`);
            const formClass = container.attr("class") || '';

            // Work out the exact prefix used (with or without a location number) and the
            // form class type to send to the backend, e.g. 'form_submit_request' or 'form_submit_request_3'
            const prefixMatch = formClass.match(new RegExp(`${basePrefix}(_\\d+)?-`));
            const classPrefix = prefixMatch ? prefixMatch[0] : `${basePrefix}-`;
            formClassType = prefixMatch ? prefixMatch[0].slice(0, -1) : basePrefix;

            // Extract the form identifier (number or text) using regex
            const match = formClass.match(new RegExp(`${classPrefix}([\\w_]+)`));
            formIdentifier = match ? match[1] : formType === 'rd' ? 'Ringo Media' : '26';

            // Format the form identifier for RepairDesk forms
            if (formType === 'rd') {
                formIdentifier = formatText(formIdentifier);
            }
        }

        // Log which form class was triggered (for debugging)
        console.log(`Form triggered: ${formClassType}`);

        const formData = {};
        let isBotDetected = false; // Flag to track bot detection
        $(this).find('input, textarea, select').each(function () {
            // Check for honeypot fields
            if ($(this).attr('name') === "form_fields[honeypot_field]" || $(this).attr('name') === "form_fields[honeypot]") {
                if ($(this).val().trim() !== "") { // Check if the honeypot has a value (bots often fill it)
                    alert("Our systems detected unusual activity. If you’re human, please avoid hidden fields and try again!");
                    isBotDetected = true;
                    window.location.reload(); // Refresh the page
                    return false; // Exit the `.each()` loop early
                }
            }

            // Skip hidden inputs
            if ($(this).attr('type') === 'hidden') {
                return true; // Continue to next iteration
            }

            const name = $(this).attr('name');
            const value = $(this).val();

            // Skip if the name is 'g-recaptcha-response'
            if (name === 'g-recaptcha-response') {
                return true; // Continue to next iteration
            }

            if (name && !isBotDetected) { // Only proceed if no bot was detected
                formData[name] = value;
            }
        });

        // Stop form submission if bot is detected
        if (isBotDetected) {
            return false; // Prevents the form from submitting
        }

        // Helper function to clean and format keys
        const formatKey = (key) => {
            let formattedKey = key.replace(/^[^\[]*\[/, '');
            // Remove the closing ']' if it exists
            formattedKey = formattedKey.replace(/\]$/, '');
            // Replace underscores and hyphens with spaces
            formattedKey = formattedKey.replace(/[_-]/g, ' ');
            // Trim any leading or trailing spaces
            formattedKey = formattedKey.trim();
            // Capitalize each word
            return formattedKey.replace(/\b\w/g, (char) => char.toUpperCase());
        };

        // Define the specific keys you want to extract
        const specificKeys = {
            'form_fields[name]': 'name',
            'form_fields[email]': 'email',
            'form_fields[phone]': 'phone',
            'form_fields[message]': 'message'
        };

        // Extract specific fields for API
        const extractedFields = {};
        for (const key in specificKeys) {
            if (formData[key]) {
                extractedFields[specificKeys[key]] = formData[key];
            } else {
                extractedFields[specificKeys[key]] = ''; // Default to empty string if not found
            }
        }

        const { name, email, phone, message } = extractedFields;

        // Pulls the plain field name out of a "form_fields[service]" style key,
        // e.g. "form_fields[service]" -> "service".
        const extractFieldKey = (key) => {
            const match = key.match(/^[^\[]*\[(.+)\]$/);
            return match ? match[1] : key;
        };

        // Handle additional (custom) fields. RingoLeads stores unrecognised fields
        // verbatim, so those get sent through as their own keys instead of being
        // folded into the message text like PBX and Repair Desk expect.
        let additionalMessage = '';
        const extraFields = {};
        for (const key in formData) {
            if (!specificKeys[key]) { // If the key is not in the specificKeys map
                if (formType === 'rl') {
                    const fieldKey = extractFieldKey(key);
                    if (fieldKey) extraFields[fieldKey] = formData[key];
                } else {
                    const formattedKey = formatKey(key); // Format the key
                    additionalMessage += `\n${formattedKey}: ${formData[key]}`;
                }
            }
        }

        // Combine the main message and additional fields (PBX / Repair Desk only)
        const finalMessage = formType === 'rl' ? message : message + additionalMessage;

        const ajaxData = {
            action: 'send_form_data_to_api',
            name: name,
            email: email,
            phone: phone,
            message: finalMessage,
            formNumber: formIdentifier,
            api: apiName,
            formClassType: formClassType // Pass the form class type (location) to the backend
        };

        if (formType === 'rl') {
            ajaxData.source_url = window.location.href;
            ajaxData.source = routeInfo ? routeInfo.source : 'wordpress';
            ajaxData.extra_fields = JSON.stringify(extraFields);
        }

        // Send data via AJAX
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                console.log('Data sent successfully:', response);
                if (!response.success) {
                    alert('Error: ' + response.data.message); // Show error message
                }
            },
            error: function (error) {
                console.error('Error sending data:', error);
                // alert('Failed to send data. Please try again.'); // Show error message
            },
        });
    }

    // Function to format text (e.g., replace underscores with spaces and capitalize words)
    function formatText(text) {
        return text
            .replace(/_/g, ' ') // Replace underscores with spaces
            .replace(/\b\w/g, (char) => char.toUpperCase()); // Capitalize each word
    }

    // Attach event listeners for both form types, across any number of locations
    // (matches 'form_submit_request-...', 'form_submit_request_2-...', 'form_submit_request_3-...', etc.)
    $(document).on('submit', "div[class*='form_submit_request'] form, form[class*='form_submit_request']", function (e) {
        handleFormSubmission.call(this, e, 'pbx');
    });

    $(document).on('submit', "div[class*='rd_form_request'] form, form[class*='rd_form_request']", function (e) {
        handleFormSubmission.call(this, e, 'rd');
    });

    // RingoLeads: one class determines both source and location.
    // Examples: rl_form_request_website, rl_form_request_google_ads, rl_form_request_google_ads_2, rl_form_request_facebook_3.
    // We listen on all forms, but only intercept forms with a recognized RingoLeads class.
    $(document).on('submit', 'form', function (e) {
        const routeInfo = findRingoLeadsRoute($(this));
        if (!routeInfo) return;
        handleFormSubmission.call(this, e, 'rl', routeInfo);
    });

    $(document).on('input', "#form-field-phone", function (e) {
        var number = this.value.replace(/[^0-9]/g, '');
        // Format as (123) 456-7890
        if (number.length > 3 && number.length <= 6) {
            number = number.replace(/(\d{3})/, '($1) ');
        } else if (number.length > 6) {
            number = number.replace(/(\d{3})(\d{3})/, '($1) $2-');
        }
        this.value = number.substring(0, 14);
    });
});
