<?php
/**
 * CrediLens AI — Generative AI Configuration
 * Supports Google Gemini API & OpenAI API with secure server-side tool calling.
 */

return [
    // Active Provider: 'gemini', 'openai', or 'local'
    'provider' => 'gemini',

    // Google Gemini API Settings
    'gemini_api_key' => '',
    'gemini_model'   => 'gemini-1.5-flash',
    'gemini_endpoint'=> 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent',

    // OpenAI API Settings
    'openai_api_key' => '',
    'openai_model'   => 'gpt-4o-mini',
    'openai_endpoint'=> 'https://api.openai.com/v1/chat/completions',

    // Assistant Persona & Guardrails
    'system_instruction' => 'You are the intelligent, professional AI Credit & Lending Assistant for CrediLens AI. You assist applicants with loan products, eligibility guidelines, KYC requirements, and EMI calculations. For staff (Loan Officers, Risk Analysts), you summarize risk assessments, fraud flags, and policy compliance. Be concise, transparent, helpful, and professional.',

    // Multi-turn context memory limit (in messages)
    'memory_limit' => 10,
];
