import { Wallet, User, MapPin, Camera, PenTool, Shield } from 'lucide-vue-next'

export interface DataSection {
    title: string
    icon: any
    fields: Array<{ label: string; value: string; key: string }>
}

const FIELD_LABELS: Record<string, string> = {
    // Wallet fields
    mobile: 'Mobile Number',
    bank_code: 'Bank/Wallet Provider',
    account_number: 'Account Number',
    amount: 'Amount',
    settlement_rail: 'Payment Method',
    recipient_country: 'Country',
    
    // Personal fields
    full_name: 'Full Name',
    email: 'Email Address',
    birth_date: 'Date of Birth',
    address: 'Address',
    
    // Verification fields
    latitude: 'Latitude',
    longitude: 'Longitude',
    selfie: 'Selfie Photo',
    signature: 'Digital Signature',
    otp_code: 'Verification Code',
}

const BANK_NAMES: Record<string, string> = {
    'GXCHPHM2XXX': 'GCash',
    'OYABPHM1XXX': 'Maya (PayMaya)',
    'BNORPHMM': 'BDO',
    'BOPIPHMM': 'BPI',
    'UBPHPHMM': 'UnionBank',
    'MBTCPHMM': 'Metrobank',
    'RCBCPHMM': 'RCBC',
    'SECDPHMM': 'Security Bank',
}

const SETTLEMENT_RAIL_LABELS: Record<string, string> = {
    'INSTAPAY': 'InstaPay (Real-time)',
    'PESONET': 'PESONet (Next Day)',
}

export interface HeroData {
    amount: string | null
    bankName: string | null
    settlementRail: string | null
}

export function useFormFlowSummary() {
    function flattenCollectedData(collectedData: any[]): Record<string, any> {
        const flattened: Record<string, any> = {}
        
        collectedData.forEach((stepData) => {
            if (stepData && typeof stepData === 'object') {
                Object.assign(flattened, stepData)
            }
        })
        
        return flattened
    }
    
    /**
     * Extract hero-level fields for prominent display.
     * Returns formatted amount, bank name, and settlement rail.
     */
    function extractHeroData(data: Record<string, any>): HeroData {
        return {
            amount: 'amount' in data ? formatFieldValue('amount', data.amount) : null,
            bankName: 'bank_code' in data ? (BANK_NAMES[data.bank_code] || data.bank_code) : null,
            settlementRail: 'settlement_rail' in data ? (SETTLEMENT_RAIL_LABELS[data.settlement_rail] || data.settlement_rail) : null,
        }
    }
    
    function formatFieldValue(key: string, value: any): string {
        if (value === null || value === undefined) return 'N/A'
        
        switch (key) {
            case 'amount':
                return `₱${Number(value).toFixed(2)}`
            
            case 'mobile': {
                const s = String(value)
                // Format +639173011987 → +63 (917) 301-1987
                const match = s.match(/^\+(\d{2})(\d{3})(\d{3})(\d{4})$/)
                return match ? `+${match[1]} (${match[2]}) ${match[3]}-${match[4]}` : s
            }
            
            case 'bank_code':
                return BANK_NAMES[value] || value
            
            case 'settlement_rail':
                return SETTLEMENT_RAIL_LABELS[value] || value
            
            case 'recipient_country':
                return value === 'PH' ? 'Philippines' : value
            
            case 'birth_date':
                try {
                    return new Date(value).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    })
                } catch {
                    return value
                }
            
            case 'latitude':
            case 'longitude':
                return Number(value).toFixed(6)
            
            case 'selfie':
            case 'signature':
                return typeof value === 'string' && value.length > 50 
                    ? '✓ Captured' 
                    : value
            
            default:
                return String(value)
        }
    }
    
    function getFieldLabel(key: string): string {
        return FIELD_LABELS[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    }
    
    function groupDataBySection(data: Record<string, any>): DataSection[] {
        const sections: DataSection[] = []
        
        // Wallet section
        const walletKeys = ['mobile', 'bank_code', 'account_number', 'amount', 'settlement_rail', 'recipient_country']
        const walletFields = walletKeys
            .filter(key => key in data)
            .map(key => ({
                key,
                label: getFieldLabel(key),
                value: formatFieldValue(key, data[key])
            }))
        
        if (walletFields.length > 0) {
            sections.push({
                title: 'Redemption Details',
                icon: Wallet,
                fields: walletFields
            })
        }
        
        // Personal info section
        const personalKeys = ['full_name', 'email', 'birth_date', 'address']
        const personalFields = personalKeys
            .filter(key => key in data)
            .map(key => ({
                key,
                label: getFieldLabel(key),
                value: formatFieldValue(key, data[key])
            }))
        
        if (personalFields.length > 0) {
            sections.push({
                title: 'Personal Information',
                icon: User,
                fields: personalFields
            })
        }
        
        // Location section
        const locationKeys = ['latitude', 'longitude', 'address']
        const locationFields = locationKeys
            .filter(key => key in data && !personalKeys.includes(key))
            .map(key => ({
                key,
                label: getFieldLabel(key),
                value: formatFieldValue(key, data[key])
            }))
        
        if (locationFields.length > 0 || 'address' in data) {
            // Combine lat/long into coordinates
            const hasCoords = 'latitude' in data && 'longitude' in data
            const finalFields = hasCoords
                ? [
                    ...(data.address ? [{
                        key: 'address',
                        label: 'Address',
                        value: String(data.address)
                    }] : []),
                    {
                        key: 'coordinates',
                        label: 'Coordinates',
                        value: `${formatFieldValue('latitude', data.latitude)}, ${formatFieldValue('longitude', data.longitude)}`
                    }
                ]
                : locationFields
            
            sections.push({
                title: 'Location Verification',
                icon: MapPin,
                fields: finalFields
            })
        }
        
        // Verification section (selfie, signature, KYC)
        const verificationFields: Array<{ key: string; label: string; value: string }> = []
        
        if ('selfie' in data) {
            verificationFields.push({
                key: 'selfie',
                label: 'Selfie',
                value: formatFieldValue('selfie', data.selfie)
            })
        }
        
        if ('signature' in data) {
            verificationFields.push({
                key: 'signature',
                label: 'Signature',
                value: formatFieldValue('signature', data.signature)
            })
        }
        
        if ('otp_code' in data) {
            verificationFields.push({
                key: 'otp_code',
                label: 'OTP Verified',
                value: '✓ Confirmed'
            })
        }
        
        if (verificationFields.length > 0) {
            sections.push({
                title: 'Identity Verification',
                icon: Shield,
                fields: verificationFields
            })
        }
        
        return sections
    }
    
    return {
        flattenCollectedData,
        extractHeroData,
        formatFieldValue,
        getFieldLabel,
        groupDataBySection
    }
}
